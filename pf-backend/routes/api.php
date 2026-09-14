<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BillingController;
use App\Http\Controllers\Api\CustomerOrderController;
use App\Http\Controllers\Api\KdsController;
use App\Http\Controllers\Api\MenuCategoryController;
use App\Http\Controllers\Api\MenuItemController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\OnboardingController;
use App\Http\Controllers\Api\OutletController;
use App\Http\Controllers\Api\PosOrderController;
use App\Http\Controllers\Api\PublicTableController;
use App\Http\Controllers\Api\RefundRequestController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\StaffController;
use App\Http\Controllers\Api\TableController;
use App\Http\Controllers\Api\TenantPaymentAccountController;
use App\Http\Controllers\Api\Webhooks\OrderPaymentWebhookController;
use App\Http\Controllers\Api\IngredientController;
use App\Http\Controllers\Api\IngredientCategoryController;
use App\Http\Controllers\Api\StockOpnameController;
use App\Http\Controllers\Api\StockHistoryController;
use App\Http\Controllers\Api\Webhooks\PlatformBillingWebhookController;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes - Lapaqu POS SaaS
|--------------------------------------------------------------------------
*/

// Broadcast Channel Authentication for API SPA (Bearer Token)
Broadcast::routes(['middleware' => ['auth:sanctum']]);

// Onboarding & Self-Service Registration (Rate Limit: 10/min)
Route::middleware('throttle:10,1')->post('/onboarding/register', [OnboardingController::class, 'register']);

// Public Customer Ordering Endpoints (Rate Limit: 60/min table, 30/min order)

// Live Dashboard Overview Route (Real database stats & dynamic chart for frontend)
Route::get('/dashboard/overview', function (Request $request) {
    $outlet = \App\Models\Outlet::first();
    $period = $request->query('period', 'today'); // 'today', 'week', 'month'

    // Determine Timezone & Date Range in Indonesian Local Time (Asia/Jakarta)
    $tz = 'Asia/Jakarta';
    $now = now()->setTimezone($tz);

    if ($period === 'week') {
        $startDate = $now->copy()->subDays(6)->startOfDay()->setTimezone('UTC');
        $endDate = $now->copy()->endOfDay()->setTimezone('UTC');

        $prevStartDate = $now->copy()->subDays(13)->startOfDay()->setTimezone('UTC');
        $prevEndDate = $now->copy()->subDays(7)->endOfDay()->setTimezone('UTC');
        $periodLabel = 'minggu lalu';
    } elseif ($period === 'month') {
        $startDate = $now->copy()->startOfMonth()->startOfDay()->setTimezone('UTC');
        $endDate = $now->copy()->endOfDay()->setTimezone('UTC');

        $prevStartDate = $now->copy()->subMonth()->startOfMonth()->startOfDay()->setTimezone('UTC');
        $prevEndDate = $now->copy()->subMonth()->endOfMonth()->endOfDay()->setTimezone('UTC');
        $periodLabel = 'bulan lalu';
    } else {
        $startDate = $now->copy()->startOfDay()->setTimezone('UTC');
        $endDate = $now->copy()->endOfDay()->setTimezone('UTC');

        $prevStartDate = $now->copy()->subDay()->startOfDay()->setTimezone('UTC');
        $prevEndDate = $now->copy()->subDay()->endOfDay()->setTimezone('UTC');
        $periodLabel = 'kemarin';
    }

    // 1. Stats Queries (Hitung semua order yang paid atau completed, exclude cancelled/voided)
    $buildValidQuery = function ($start, $end) {
        return \App\Models\Order::whereBetween('created_at', [$start, $end])
            ->where(function ($q) {
                $q->where('payment_status', 'paid')
                  ->orWhere('status', 'completed');
            })
            ->whereNotIn('status', ['cancelled', 'voided', 'refunded']);
    };

    $currentOrdersQuery = $buildValidQuery($startDate, $endDate);
    $totalOrders = $currentOrdersQuery->count();
    $totalRevenue = (int) $currentOrdersQuery->sum('final_amount');
    $totalCustomers = $currentOrdersQuery->whereNotNull('customer_name')->distinct('customer_name')->count('customer_name');
    if ($totalCustomers == 0) {
        $totalCustomers = $totalOrders > 0 ? (int) ceil($totalOrders * 0.8) : 0;
    }

    // Previous Period Stats for Trends
    $prevOrdersQuery = $buildValidQuery($prevStartDate, $prevEndDate);
    $prevTotalOrders = $prevOrdersQuery->count();
    $prevTotalRevenue = (int) $prevOrdersQuery->sum('final_amount');
    $prevTotalCustomers = $prevOrdersQuery->whereNotNull('customer_name')->distinct('customer_name')->count('customer_name');
    if ($prevTotalCustomers == 0) {
        $prevTotalCustomers = $prevTotalOrders > 0 ? (int) ceil($prevTotalOrders * 0.8) : 0;
    }

    // Antrean Pending (Active orders waiting payment or kitchen)
    $pendingOrders = \App\Models\Order::whereIn('status', [
        'pending_payment', 'confirmed', 'processing', 'preparing', 'cooking', 'ready'
    ])->count();

    // Helper calculate trend percentage
    $calcTrend = function ($curr, $prev, $label) {
        if ($prev == 0) {
            if ($curr == 0) {
                return ['value' => '0.0%', 'isPositive' => true, 'label' => "Stabil vs {$label}"];
            }
            return ['value' => '+100%', 'isPositive' => true, 'label' => "Naik dari {$label}"];
        }
        $diff = $curr - $prev;
        $pct = round(($diff / $prev) * 100, 1);
        $isPos = $pct >= 0;
        $prefix = $isPos ? '+' : '';
        $word = $isPos ? 'Naik dari' : 'Turun dari';
        return [
            'value' => "{$prefix}{$pct}%",
            'isPositive' => $isPos,
            'label' => "{$word} {$label}",
        ];
    };

    $trends = [
        'customers' => $calcTrend($totalCustomers, $prevTotalCustomers, $periodLabel),
        'orders' => $calcTrend($totalOrders, $prevTotalOrders, $periodLabel),
        'sales' => $calcTrend($totalRevenue, $prevTotalRevenue, $periodLabel),
        'pending' => [
            'value' => "{$pendingOrders} pesanan",
            'isPositive' => $pendingOrders <= 5,
            'label' => 'Menunggu dapur',
        ],
    ];

    // 2. Dynamic Chart Data Calculation
    $chartLabels = [];
    $chartValues = [];

    if ($period === 'today') {
        $hours = ['08:00', '10:00', '12:00', '14:00', '16:00', '18:00', '20:00', '22:00'];
        $hourlyMap = array_fill_keys($hours, 0);

        $orders = \App\Models\Order::whereBetween('created_at', [$startDate, $endDate])
            ->where(function ($q) {
                $q->where('payment_status', 'paid')->orWhere('status', 'completed');
            })
            ->whereNotIn('status', ['cancelled', 'voided', 'refunded'])
            ->get();

        foreach ($orders as $order) {
            $hInt = (int) $order->created_at->setTimezone('Asia/Jakarta')->format('H');
            $bracket = sprintf('%02d:00', min(22, max(8, floor($hInt / 2) * 2)));
            if (isset($hourlyMap[$bracket])) {
                $hourlyMap[$bracket] += (int) $order->final_amount;
            }
        }
        $chartLabels = array_keys($hourlyMap);
        $chartValues = array_values($hourlyMap);
    } elseif ($period === 'week') {
        for ($i = 6; $i >= 0; $i--) {
            $dayDate = $now->copy()->subDays($i);
            $dayLabel = $dayDate->isoFormat('ddd');
            $dayStart = $dayDate->copy()->startOfDay()->setTimezone('UTC');
            $dayEnd = $dayDate->copy()->endOfDay()->setTimezone('UTC');
            $rev = (int) \App\Models\Order::whereBetween('created_at', [$dayStart, $dayEnd])
                ->where(function ($q) {
                    $q->where('payment_status', 'paid')->orWhere('status', 'completed');
                })
                ->whereNotIn('status', ['cancelled', 'voided', 'refunded'])
                ->sum('final_amount');
            $chartLabels[] = $dayLabel;
            $chartValues[] = $rev;
        }
    } else {
        $daysInMonth = $now->daysInMonth;
        for ($d = 1; $d <= $daysInMonth; $d += 5) {
            $dayDate = $now->copy()->setDay(min($d, $daysInMonth));
            $dayLabel = $dayDate->format('d M');
            $dayStart = $dayDate->copy()->startOfDay()->setTimezone('UTC');
            $dayEnd = $dayDate->copy()->addDays(4)->endOfDay()->setTimezone('UTC');
            $rev = (int) \App\Models\Order::whereBetween('created_at', [$dayStart, $dayEnd])
                ->where(function ($q) {
                    $q->where('payment_status', 'paid')->orWhere('status', 'completed');
                })
                ->whereNotIn('status', ['cancelled', 'voided', 'refunded'])
                ->sum('final_amount');
            $chartLabels[] = $dayLabel;
            $chartValues[] = $rev;
        }
    }

    $maxVal = 0;
    $maxIdx = 0;
    foreach ($chartValues as $idx => $val) {
        if ($val > $maxVal) {
            $maxVal = $val;
            $maxIdx = $idx;
        }
    }

    // 3. Recent Deals (Latest real database orders)
    $recentOrders = \App\Models\Order::with(['table', 'items'])->latest()->take(50)->get()->map(function ($order) {
        $firstItem = $order->items->first();
        $itemCount = $order->items->count();
        $title = $firstItem ? $firstItem->item_name_snapshot : 'Pesanan Resto';
        if ($itemCount > 1) {
            $title .= ' (+' . ($itemCount - 1) . ' item)';
        }

        $status = 'Delivered';
        if (in_array($order->status, ['pending_payment', 'confirmed', 'processing', 'preparing', 'cooking', 'ready'])) {
            $status = 'Pending';
        } elseif (in_array($order->status, ['cancelled', 'voided', 'refunded'])) {
            $status = 'Rejected';
        }

        return [
            'id' => $order->id,
            'order_number' => $order->order_number,
            'product_name' => $title,
            'location' => $order->table ? $order->table->table_number . ' (Dine In)' : 'Takeaway',
            'date_time' => $order->created_at->setTimezone('Asia/Jakarta')->format('d.m.Y - h:i A'),
            'piece' => $order->items->sum('quantity'),
            'amount' => (int) $order->final_amount,
            'status' => $status,
        ];
    });

    return response()->json([
        'outlet' => [
            'id' => $outlet?->id,
            'name' => $outlet?->name ?? 'Cabang Senopati Utama',
            'tenant' => $outlet?->tenant?->name ?? 'Kopi Kenangan Senopati',
        ],
        'period' => $period,
        'stats' => [
            'total_customers' => $totalCustomers,
            'total_orders' => $totalOrders,
            'total_sales' => $totalRevenue,
            'total_pending' => $pendingOrders,
            'trends' => $trends,
        ],
        'chart' => [
            'labels' => $chartLabels,
            'values' => $chartValues,
            'peak' => [
                'value' => $maxVal,
                'label' => $chartLabels[$maxIdx] ?? '',
                'index' => $maxIdx,
                'total_points' => count($chartLabels),
            ],
        ],
        'deals' => $recentOrders,
    ]);
});

Route::prefix('public')->group(function () {
    Route::middleware('throttle:60,1')->get('/tables/{token}', [PublicTableController::class, 'resolveTable']);
    Route::middleware('throttle:30,1')->post('/orders', [CustomerOrderController::class, 'store']);
    Route::get('/orders/active', [CustomerOrderController::class, 'activeOrder']);
    Route::middleware('throttle:60,1')->get('/orders/{id}/status', [CustomerOrderController::class, 'status']);
    Route::post('/orders/{id}/simulate-pay', [CustomerOrderController::class, 'simulatePay']);
    Route::post('/orders/{id}/cancel', [CustomerOrderController::class, 'cancel']);
    Route::post('/orders/{id}/expire', [CustomerOrderController::class, 'expire']);
});

// Public Authentication Endpoints
Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/2fa/challenge', [AuthController::class, 'challenge2FA']);
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('/reset-password', [AuthController::class, 'resetPassword']);

    // Protected Auth Endpoints
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/refresh', [AuthController::class, 'refresh']);
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::post('/2fa/setup', [AuthController::class, 'setup2FA']);
        Route::post('/2fa/verify', [AuthController::class, 'verify2FA']);
    });
});

// Tenant Protected Routes (Staff / Owner / Kasir / Kitchen)
// Public Menu Browsing (Accessible by Customers & Guests without auth)
Route::get('/menu-categories', [MenuCategoryController::class, 'index']);
Route::get('/menu-items', [MenuItemController::class, 'index']);
Route::get('/menu-items/{id}', [MenuItemController::class, 'show']);

Route::middleware(['auth:sanctum', 'tenant.subscription'])->group(function () {
    // Menu Categories (Staff Mutations)
    Route::post('/menu-categories', [MenuCategoryController::class, 'store']);
    Route::put('/menu-categories/{id}', [MenuCategoryController::class, 'update']);
    Route::delete('/menu-categories/{id}', [MenuCategoryController::class, 'destroy']);

    // Menu Items & Variants (Staff Mutations)
    Route::post('/menu-items', [MenuItemController::class, 'store']);
    Route::put('/menu-items/{id}', [MenuItemController::class, 'update']);
    Route::patch('/menu-items/{id}/toggle-availability', [MenuItemController::class, 'toggleAvailability']);
    Route::delete('/menu-items/{id}', [MenuItemController::class, 'destroy']);
    Route::get('/menu-items/{id}/recipe', [MenuItemController::class, 'getRecipe']);
    Route::put('/menu-items/{id}/recipe', [MenuItemController::class, 'updateRecipe']);

    // Ingredients (Bahan Baku)
    Route::get('/ingredients', [IngredientController::class, 'index']);
    Route::post('/ingredients', [IngredientController::class, 'store']);
    Route::get('/ingredients/{id}', [IngredientController::class, 'show']);
    Route::put('/ingredients/{id}', [IngredientController::class, 'update']);
    Route::delete('/ingredients/{id}', [IngredientController::class, 'destroy']);
    Route::post('/ingredients/{id}/adjust-stock', [IngredientController::class, 'adjustStock']);

    // Ingredient Categories (Kategori Bahan Baku)
    Route::get('/ingredient-categories', [IngredientCategoryController::class, 'index']);
    Route::post('/ingredient-categories', [IngredientCategoryController::class, 'store']);
    Route::get('/ingredient-categories/{id}', [IngredientCategoryController::class, 'show']);
    Route::put('/ingredient-categories/{id}', [IngredientCategoryController::class, 'update']);
    Route::delete('/ingredient-categories/{id}', [IngredientCategoryController::class, 'destroy']);

    // Stock Opname
    Route::get('/stock-opnames', [StockOpnameController::class, 'index']);
    Route::post('/stock-opnames', [StockOpnameController::class, 'store']);
    Route::get('/stock-opnames/{id}', [StockOpnameController::class, 'show']);

    // Stock Logs / History
    Route::get('/stock-logs', [StockHistoryController::class, 'index']);


    // Tables & QR Code
    Route::get('/tables', [TableController::class, 'index']);
    Route::post('/tables', [TableController::class, 'store']);
    Route::put('/tables/{id}', [TableController::class, 'update']);
    Route::delete('/tables/{id}', [TableController::class, 'destroy']);
    Route::post('/tables/{id}/regenerate-qr', [TableController::class, 'regenerateQr']);
    Route::get('/tables/{id}/qr-code', [TableController::class, 'qrCodeSvg']);

    // POS Kasir Routes (Kasir & Owner)
    Route::middleware('role:owner,kasir')->prefix('pos')->group(function () {
        Route::get('/orders', [PosOrderController::class, 'index']);
        Route::post('/orders', [PosOrderController::class, 'store']);
        Route::patch('/orders/{id}/status', [PosOrderController::class, 'updateStatus']);
        Route::post('/orders/{id}/pay-cash', [PosOrderController::class, 'payCash']);
        Route::post('/orders/{id}/void-item', [PosOrderController::class, 'voidItem']);
        Route::post('/tables/{id}/close-session', [PosOrderController::class, 'closeTableSession']);
    });

    // Kitchen Display System (KDS) Routes (Kitchen Staff, Kasir & Owner)
    Route::middleware('role:owner,kitchen_staff,kasir')->prefix('kds')->group(function () {
        Route::get('/orders', [KdsController::class, 'orders']);
        Route::patch('/items/{id}/status', [KdsController::class, 'updateItemStatus']);
    });

    // Refunds (Submission & List)
    Route::post('/orders/{id}/refund', [RefundRequestController::class, 'store']);
    Route::get('/refunds', [RefundRequestController::class, 'index']);

    // In-App Notifications
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::patch('/notifications/{id}/read', [NotificationController::class, 'markAsRead']);

    // Owner-only Management & Analytics Routes
    Route::middleware('role:owner')->group(function () {
        // Tenant Billing
        Route::prefix('billing')->group(function () {
            Route::get('/subscription', [BillingController::class, 'currentSubscription']);
            Route::get('/invoices', [BillingController::class, 'invoices']);
            Route::post('/invoices/create', [BillingController::class, 'createInvoice']);
        });

        // Tenant Payment Account & xenPlatform
        Route::get('/payment-account', [TenantPaymentAccountController::class, 'show']);
        Route::post('/payment-account', [TenantPaymentAccountController::class, 'store']);
        Route::get('/payment-account/settlement-logs', [TenantPaymentAccountController::class, 'settlementLogs']);

        // Staff Management
        Route::get('/staff', [StaffController::class, 'index']);
        Route::post('/staff', [StaffController::class, 'store']);
        Route::put('/staff/{id}', [StaffController::class, 'update']);
        Route::delete('/staff/{id}', [StaffController::class, 'destroy']);

        // Outlet Management
        Route::get('/outlets', [OutletController::class, 'index']);
        Route::post('/outlets', [OutletController::class, 'store']);
        Route::put('/outlets/{id}', [OutletController::class, 'update']);

    });

    // Refund Approvals & Reports (Owner, Manager & Kasir)
    Route::middleware('role:owner,manager,kasir')->group(function () {
        Route::post('/refunds/{id}/approve', [RefundRequestController::class, 'approve']);
        Route::post('/refunds/{id}/reject', [RefundRequestController::class, 'reject']);

        Route::prefix('reports')->group(function () {
            Route::get('/sales-summary', [ReportController::class, 'salesSummary']);
            Route::get('/top-items', [ReportController::class, 'topItems']);
            Route::get('/hourly-sales', [ReportController::class, 'hourlySales']);
            Route::get('/export-csv', [ReportController::class, 'exportCsv']);
        });
    });
});

// Xendit Webhooks
Route::post('/webhooks/xendit/platform-billing', [PlatformBillingWebhookController::class, 'handle']);
Route::post('/webhooks/xendit/order-payment', [OrderPaymentWebhookController::class, 'handle']);

// Tenant Subdomain Checker (Rate limit: 30/IP/min)
Route::middleware('throttle:30,1')->post('/tenant/check-subdomain', function (Request $request) {
    $request->validate([
        'subdomain' => ['required', 'string', 'min:3', 'max:50', 'regex:/^[a-z0-9]+(-[a-z0-9]+)*$/'],
    ]);

    $subdomain = strtolower($request->subdomain);
    $reserved = ['admin', 'api', 'app', 'pos', 'kds', 'owner', 'superadmin', 'billing', 'www', 'mail', 'auth', 'test'];

    if (in_array($subdomain, $reserved)) {
        return response()->json([
            'available' => false,
            'reason' => 'Subdomain ini adalah kata yang dicadangkan oleh sistem.',
        ], 422);
    }

    $exists = Tenant::where('subdomain', $subdomain)->exists();

    return response()->json([
        'subdomain' => $subdomain,
        'available' => !$exists,
    ]);
});

