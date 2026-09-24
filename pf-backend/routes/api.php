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
use App\Http\Controllers\Api\CashierShiftController;
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
Route::middleware('throttle:10,1')->post('/onboarding/send-otp', [OnboardingController::class, 'sendOtp']);
Route::middleware('throttle:10,1')->post('/onboarding/register', [OnboardingController::class, 'register']);

// Public Customer Ordering Endpoints (Rate Limit: 60/min table, 30/min order)

// Live Dashboard Overview Route (Real database stats & dynamic chart for frontend)
Route::get('/dashboard/overview', function (Request $request) {
    $user = auth('sanctum')->user();
    $outlet = $user?->outlet ?? $user?->tenant?->outlets()->first() ?? \App\Models\Outlet::where('name', 'POS Self Order')->first() ?? \App\Models\Outlet::first();
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
    } elseif ($period === 'year') {
        $startDate = $now->copy()->startOfYear()->startOfDay()->setTimezone('UTC');
        $endDate = $now->copy()->endOfDay()->setTimezone('UTC');

        $prevStartDate = $now->copy()->subYear()->startOfYear()->startOfDay()->setTimezone('UTC');
        $prevEndDate = $now->copy()->subYear()->endOfYear()->endOfDay()->setTimezone('UTC');
        $periodLabel = 'tahun lalu';
    } else {
        $startDate = $now->copy()->startOfDay()->setTimezone('UTC');
        $endDate = $now->copy()->endOfDay()->setTimezone('UTC');

        $prevStartDate = $now->copy()->subDay()->startOfDay()->setTimezone('UTC');
        $prevEndDate = $now->copy()->subDay()->endOfDay()->setTimezone('UTC');
        $periodLabel = 'kemarin';
    }

    // 1. Optimized Single Stats Queries (Hitung semua order yang paid atau completed, exclude cancelled/voided)
    $currentStats = \App\Models\Order::whereBetween('created_at', [$startDate, $endDate])
        ->where(function ($q) {
            $q->where('payment_status', 'paid')->orWhere('status', 'completed');
        })
        ->whereNotIn('status', ['cancelled', 'voided', 'refunded'])
        ->selectRaw('COUNT(*) as total_orders, COALESCE(SUM(final_amount), 0) as total_revenue, COUNT(DISTINCT customer_name) as total_customers')
        ->first();

    $totalOrders = (int) ($currentStats->total_orders ?? 0);
    $totalRevenue = (int) ($currentStats->total_revenue ?? 0);
    $totalCustomers = (int) ($currentStats->total_customers ?? 0);

    // Previous Period Stats for Trends in single query
    $prevStats = \App\Models\Order::whereBetween('created_at', [$prevStartDate, $prevEndDate])
        ->where(function ($q) {
            $q->where('payment_status', 'paid')->orWhere('status', 'completed');
        })
        ->whereNotIn('status', ['cancelled', 'voided', 'refunded'])
        ->selectRaw('COUNT(*) as total_orders, COALESCE(SUM(final_amount), 0) as total_revenue, COUNT(DISTINCT customer_name) as total_customers')
        ->first();

    $prevTotalOrders = (int) ($prevStats->total_orders ?? 0);
    $prevTotalRevenue = (int) ($prevStats->total_revenue ?? 0);
    $prevTotalCustomers = (int) ($prevStats->total_customers ?? 0);

    // Antrean Pending (Active orders waiting payment or kitchen)
    $pendingOrders = \App\Models\Order::whereIn('status', [
        'pending_payment', 'confirmed', 'processing', 'preparing', 'cooking', 'ready'
    ])->count();

    // Helper calculate trend percentage
    $calcTrend = function ($curr, $prev, $label) {
        // Jika data saat ini masih 0, buat 0% warna abu-abu netral
        if ($curr <= 0) {
            return [
                'value' => '0%',
                'isPositive' => false,
                'isNeutral' => true,
                'label' => "vs {$label}",
                'icon' => 'remove',
            ];
        }

        // Jika periode sebelumnya 0 dan saat ini ada data transaksi
        if ($prev <= 0) {
            return [
                'value' => '+100%',
                'isPositive' => true,
                'isNeutral' => false,
                'label' => "Naik dari {$label}",
                'icon' => 'arrow_upward',
            ];
        }

        $diff = $curr - $prev;
        $pct = round(($diff / $prev) * 100, 1);
        $isZero = $pct == 0;
        $isPos = $pct > 0;
        $formattedVal = (floor($pct) == $pct ? (int)$pct : $pct) . '%';

        if ($isZero) {
            return [
                'value' => '0%',
                'isPositive' => false,
                'isNeutral' => true,
                'label' => "Stabil vs {$label}",
                'icon' => 'remove',
            ];
        }

        $prefix = $isPos ? '+' : '';
        $word = $isPos ? 'Naik dari' : 'Turun dari';
        return [
            'value' => "{$prefix}{$formattedVal}",
            'isPositive' => $isPos,
            'isNeutral' => false,
            'label' => "{$word} {$label}",
            'icon' => $isPos ? 'arrow_upward' : 'arrow_downward',
        ];
    };

    $trends = [
        'customers' => $calcTrend($totalCustomers, $prevTotalCustomers, $periodLabel),
        'orders' => $calcTrend($totalOrders, $prevTotalOrders, $periodLabel),
        'sales' => $calcTrend($totalRevenue, $prevTotalRevenue, $periodLabel),
        'pending' => [
            'value' => "{$pendingOrders} pesanan",
            'isPositive' => $pendingOrders <= 5,
            'isNeutral' => false,
            'label' => $pendingOrders == 0 ? 'Dapur lancar' : 'Menunggu dapur',
            'icon' => $pendingOrders == 0 ? 'check_circle' : 'schedule',
        ],
    ];

    // 2. Dynamic Chart Data Calculation (Single query, in-memory bucketing)
    $chartLabels = [];
    $chartValues = [];

    $ordersForChart = \App\Models\Order::whereBetween('created_at', [$startDate, $endDate])
        ->where(function ($q) {
            $q->where('payment_status', 'paid')->orWhere('status', 'completed');
        })
        ->whereNotIn('status', ['cancelled', 'voided', 'refunded'])
        ->get(['created_at', 'final_amount']);

    if ($period === 'today') {
        $hours = ['08:00', '10:00', '12:00', '14:00', '16:00', '18:00', '20:00', '22:00'];
        $hourlyMap = array_fill_keys($hours, 0);

        foreach ($ordersForChart as $order) {
            $hInt = (int) $order->created_at->setTimezone('Asia/Jakarta')->format('H');
            $bracket = sprintf('%02d:00', min(22, max(8, floor($hInt / 2) * 2)));
            if (isset($hourlyMap[$bracket])) {
                $hourlyMap[$bracket] += (int) $order->final_amount;
            }
        }
        $chartLabels = array_keys($hourlyMap);
        $chartValues = array_values($hourlyMap);
    } elseif ($period === 'week') {
        $dayMap = [];
        for ($i = 6; $i >= 0; $i--) {
            $d = $now->copy()->subDays($i);
            $key = $d->format('Y-m-d');
            $dayMap[$key] = [
                'label' => $d->isoFormat('ddd'),
                'sum' => 0,
            ];
        }
        foreach ($ordersForChart as $order) {
            $key = $order->created_at->setTimezone('Asia/Jakarta')->format('Y-m-d');
            if (isset($dayMap[$key])) {
                $dayMap[$key]['sum'] += (int) $order->final_amount;
            }
        }
        $chartLabels = array_column($dayMap, 'label');
        $chartValues = array_column($dayMap, 'sum');
    } elseif ($period === 'year') {
        $months = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
        $monthMap = array_fill_keys(range(1, 12), 0);
        foreach ($ordersForChart as $order) {
            $mInt = (int) $order->created_at->setTimezone('Asia/Jakarta')->format('n');
            if (isset($monthMap[$mInt])) {
                $monthMap[$mInt] += (int) $order->final_amount;
            }
        }
        $chartLabels = $months;
        $chartValues = array_values($monthMap);
    } else {
        $daysInMonth = $now->daysInMonth;
        $buckets = [];
        for ($d = 1; $d <= $daysInMonth; $d += 5) {
            $dayEnd = min($d + 4, $daysInMonth);
            $dayLabel = sprintf('%02d-%02d %s', $d, $dayEnd, $now->isoFormat('MMM'));
            $buckets[] = [
                'label' => $dayLabel,
                'start' => $d,
                'end' => $dayEnd,
                'sum' => 0,
            ];
        }
        foreach ($ordersForChart as $order) {
            $day = (int) $order->created_at->setTimezone('Asia/Jakarta')->format('j');
            foreach ($buckets as &$b) {
                if ($day >= $b['start'] && $day <= $b['end']) {
                    $b['sum'] += (int) $order->final_amount;
                    break;
                }
            }
            unset($b);
        }
        $chartLabels = array_column($buckets, 'label');
        $chartValues = array_column($buckets, 'sum');
    }

    $maxVal = 0;
    $maxIdx = 0;
    foreach ($chartValues as $idx => $val) {
        if ($val > $maxVal) {
            $maxVal = $val;
            $maxIdx = $idx;
        }
    }

    // 3. Recent Deals (Latest real database orders with real item image)
    $recentOrders = \App\Models\Order::with(['table', 'items.menuItem'])->latest()->take(20)->get()->map(function ($order) {
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
            'avatar' => $firstItem?->menuItem?->image_url,
            'location' => $order->table ? $order->table->table_number . ' (Dine In)' : 'Takeaway',
            'date_time' => $order->created_at->setTimezone('Asia/Jakarta')->format('d.m.Y - h:i A'),
            'raw_date' => $order->created_at->toIso8601String(),
            'piece' => $order->items->sum('quantity'),
            'amount' => (int) $order->final_amount,
            'status' => $status,
        ];
    });

    return response()->json([
        'outlet' => [
            'id' => $outlet?->id,
            'name' => $outlet?->name ?? 'Nama Cabang',
            'tenant' => $outlet?->tenant?->name ?? ('Toko ' . ($user?->name ?? 'Saya')),
        ],
        'date' => [
            'formatted' => $now->locale('id')->isoFormat('dddd, D MMMM Y'),
            'iso' => $now->toIso8601String(),
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

Route::prefix('auth')->group(function () {
    Route::get('/google', [AuthController::class, 'redirectToGoogle']);
    Route::get('/google/callback', [AuthController::class, 'handleGoogleCallback']);
    Route::get('/facebook', [AuthController::class, 'redirectToFacebook']);
    Route::get('/facebook/callback', [AuthController::class, 'handleFacebookCallback']);
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/2fa/challenge', [AuthController::class, 'challenge2FA']);
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('/reset-password', [AuthController::class, 'resetPassword']);
    Route::post('/outlet-pairing', [AuthController::class, 'outletPairing']);
    Route::get('/outlet-staff', [AuthController::class, 'getOutletStaff']);
    Route::post('/staff-pin-login', [AuthController::class, 'staffPinLogin']);
    Route::get('/staff-pin-status/{userId}', [AuthController::class, 'getStaffPinStatus']);

    // Protected Auth Endpoints
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/me', [AuthController::class, 'me']);
        Route::put('/profile', [AuthController::class, 'updateProfile']);
        Route::put('/password', [AuthController::class, 'updatePassword']);
        Route::post('/refresh', [AuthController::class, 'refresh']);
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::post('/2fa/setup', [AuthController::class, 'setup2FA']);
        Route::post('/2fa/verify', [AuthController::class, 'verify2FA']);
        Route::post('/select-outlet', [AuthController::class, 'selectOutlet']);
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
    Route::middleware('role:owner,store_manager,kitchen_staff,kasir,superadmin')->prefix('pos')->group(function () {
        Route::get('/orders', [PosOrderController::class, 'index']);
        Route::post('/orders', [PosOrderController::class, 'store']);
        Route::patch('/orders/{id}/status', [PosOrderController::class, 'updateStatus']);
        Route::post('/orders/{id}/pay-cash', [PosOrderController::class, 'payCash']);
        Route::post('/orders/{id}/void-item', [PosOrderController::class, 'voidItem']);
        Route::post('/tables/{id}/close-session', [PosOrderController::class, 'closeTableSession']);
        Route::get('/shifts/current', [CashierShiftController::class, 'current']);
        Route::post('/shifts/open', [CashierShiftController::class, 'open']);
        Route::post('/shifts/{id}/close', [CashierShiftController::class, 'close']);
    });

    // Kitchen Display System (KDS) Routes (Kitchen Staff, Kasir & Owner)
    Route::middleware('role:owner,store_manager,kitchen_staff,kasir,superadmin')->prefix('kds')->group(function () {
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
            Route::post('/change-plan', [BillingController::class, 'changePlan']);
        });

        // Tenant Payment Account & xenPlatform
        Route::get('/payment-account', [TenantPaymentAccountController::class, 'show']);
        Route::post('/payment-account', [TenantPaymentAccountController::class, 'store']);
        Route::get('/payment-account/settlement-logs', [TenantPaymentAccountController::class, 'settlementLogs']);

        // Outlet Management (Owner Only)
        Route::get('/outlets', [OutletController::class, 'index']);
        Route::post('/outlets', [OutletController::class, 'store']);
        Route::put('/outlets/{id}', [OutletController::class, 'update']);
        Route::delete('/outlets/{id}', [OutletController::class, 'destroy']);
        Route::post('/outlets/{id}/pairing-code', [OutletController::class, 'generatePairingCode']);
        Route::get('/outlets/{id}/pairing-code', [OutletController::class, 'getActivePairingCode']);
        Route::get('/outlets/{id}/devices', [OutletController::class, 'getConnectedDevices']);
        Route::delete('/outlets/{id}/devices/{deviceId}', [OutletController::class, 'disconnectDevice']);
    });

    // POS/KDS Devices & Staff Management (Owner & Store Manager)
    Route::middleware('role:owner,store_manager,superadmin')->group(function () {

        Route::get('/staff', [StaffController::class, 'index']);
        Route::post('/staff', [StaffController::class, 'store']);
        Route::put('/staff/{id}', [StaffController::class, 'update']);
        Route::delete('/staff/{id}', [StaffController::class, 'destroy']);
    });

    // Refund Approvals & Reports (Owner, Manager & Kasir)
    Route::middleware('role:owner,store_manager,manager,kasir')->group(function () {
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

