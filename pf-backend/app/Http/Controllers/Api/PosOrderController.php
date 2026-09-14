<?php

namespace App\Http\Controllers\Api;

use App\Events\OrderCreatedEvent;
use App\Events\OrderPaidEvent;
use App\Events\OrderStatusUpdatedEvent;
use App\Http\Controllers\Controller;
use App\Models\MenuItem;
use App\Models\MenuItemVariantOption;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderItemOption;
use App\Models\Payment;
use App\Models\TableSession;
use App\Services\IngredientStockService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PosOrderController extends Controller
{
    public function __construct(protected IngredientStockService $stockService)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $outletId = $request->query('outlet_id', $request->user()->outlet_id);
        $status = $request->query('status');
        $date = $request->query('date');

        // Auto-expire pending_payment orders whose payment deadline has passed
        $pendingOrders = Order::where('status', 'pending_payment')
            ->whereIn('payment_status', ['unpaid', 'pending'])
            ->with(['payments', 'items.menuItem.recipes.ingredient'])
            ->get();

        if ($pendingOrders->isNotEmpty()) {
            foreach ($pendingOrders as $pendingOrder) {
                $pendingPayment = $pendingOrder->payments->firstWhere('status', 'pending');
                $expirationDate = $pendingPayment?->raw_payload['expiration_date'] ?? null;
                $isExpired = false;
                if ($expirationDate && now()->isAfter($expirationDate)) {
                    $isExpired = true;
                } elseif (!$expirationDate && $pendingOrder->created_at->addMinutes(15)->isPast()) {
                    $isExpired = true;
                }

                if ($isExpired) {
                    DB::transaction(function () use ($pendingOrder, $pendingPayment) {
                        $pendingOrder->status = 'expired';
                        $pendingOrder->payment_status = 'expired';
                        $pendingOrder->save();

                        if ($pendingPayment) {
                            $pendingPayment->status = 'expired';
                            $pendingPayment->save();
                        }

                        foreach ($pendingOrder->items as $item) {
                            if (!$item->is_voided) {
                                $this->stockService->returnForVoidedItem($item);
                            }
                        }

                        if ($pendingOrder->outlet_id) {
                            $this->stockService->syncMenuAvailability($pendingOrder->outlet_id);
                        }
                    });

                    try {
                        event(new OrderStatusUpdatedEvent($pendingOrder));
                    } catch (\Throwable $e) {}
                }
            }
        }

        $query = Order::with(['table', 'items.options', 'payments']);

        if ($outletId) {
            $query->where('outlet_id', $outletId);
        }

        if ($status) {
            $query->where('status', $status);
        }

        // Jika ada filter tanggal spesifik dari frontend, filter berdasarkan tanggal tersebut
        if ($date && $date !== 'all') {
            $query->whereDate('created_at', $date);
        }

        $orders = $query->orderBy('created_at', 'desc')->get();

        return response()->json(['orders' => $orders]);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        $tenantId = $user->tenant_id;

        // Auto-fill outlet_id if not present
        $outletId = $request->input('outlet_id', $request->input('outletId'))
            ?: ($user->outlet_id ?: \App\Models\Outlet::where('tenant_id', $tenantId)->first()?->id ?: \App\Models\Outlet::first()?->id);

        $orderType = $request->input('order_type', $request->input('orderType', 'dine_in'));
        $customerName = $request->input('customer_name', $request->input('customerName'));
        $rawTableId = $request->input('table_id', $request->input('tableId'));
        $tableId = (!empty($rawTableId) && $rawTableId !== '-') ? $rawTableId : null;
        $paymentMethod = strtolower($request->input('payment_method', $request->input('paymentMethod', 'cash')));
        $cashReceived = (int) $request->input('cash_received', $request->input('cashReceived', 0));

        // Normalize items array
        $rawItems = $request->input('items', []);
        $normalizedItems = [];
        if (is_array($rawItems)) {
            foreach ($rawItems as $it) {
                $menuItemId = $it['menu_item_id'] ?? ($it['menuItem']['id'] ?? ($it['id'] ?? null));
                $selectedOptionIds = [];
                $rawOpts = $it['selected_option_ids'] ?? ($it['selectedOptions'] ?? []);
                if (is_array($rawOpts)) {
                    foreach ($rawOpts as $opt) {
                        if (is_string($opt)) {
                            $selectedOptionIds[] = $opt;
                        } elseif (is_array($opt)) {
                            $selectedOptionIds[] = $opt['optionId'] ?? ($opt['id'] ?? null);
                        }
                    }
                }
                $normalizedItems[] = [
                    'menu_item_id' => $menuItemId,
                    'quantity' => (int) ($it['quantity'] ?? 1),
                    'notes' => $it['notes'] ?? null,
                    'selected_option_ids' => array_values(array_filter($selectedOptionIds)),
                ];
            }
        }

        $request->merge([
            'outlet_id' => $outletId,
            'order_type' => $orderType,
            'customer_name' => $customerName,
            'table_id' => $tableId,
            'payment_method' => $paymentMethod,
            'cash_received' => $cashReceived,
            'items' => $normalizedItems,
        ]);

        $request->validate([
            'outlet_id' => ['required', 'uuid', 'exists:outlets,id'],
            'customer_name' => ['nullable', 'string', 'max:100'],
            'table_id' => ['nullable', 'uuid', 'exists:tables,id'],
            'order_type' => ['required', 'string', 'in:dine_in,takeaway'],
            'payment_method' => ['nullable', 'string', 'in:cash,qris,card,debit,credit'],
            'cash_received' => ['nullable', 'integer', 'min:0'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.menu_item_id' => ['required', 'uuid'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.notes' => ['nullable', 'string', 'max:150'],
            'items.*.selected_option_ids' => ['nullable', 'array'],
            'items.*.selected_option_ids.*' => ['uuid'],
        ]);

        // Check ingredient availability before creating order
        $availabilityCheck = $this->stockService->checkAvailability($request->items, $request->outlet_id);
        if (!$availabilityCheck['available']) {
            return response()->json([
                'message' => 'Stok bahan tidak mencukupi.',
                'errors' => $availabilityCheck['errors'],
            ], 422);
        }

        $order = DB::transaction(function () use ($request, $user, $tenantId) {
            // Handle table session
            $tableSessionId = null;
            if ($request->table_id && $request->order_type === 'dine_in') {
                $session = TableSession::where('table_id', $request->table_id)
                    ->where('status', 'active')
                    ->first();

                if (!$session) {
                    $session = TableSession::create([
                        'tenant_id' => $tenantId,
                        'outlet_id' => $request->outlet_id,
                        'table_id' => $request->table_id,
                        'session_code' => 'SES-' . strtoupper(Str::random(8)),
                        'status' => 'active',
                        'opened_at' => now(),
                    ]);
                }

                $tableSessionId = $session->id;
            }

            // Generate order number
            $datePrefix = Carbon::now()->format('Ymd');
            $randomSuffix = strtoupper(Str::random(4));
            $orderNumber = "ORD-{$datePrefix}-{$randomSuffix}";

            // Process items
            $subtotal = 0;
            $orderItemsData = [];

            foreach ($request->items as $itemInput) {
                $menuItem = MenuItem::where('id', $itemInput['menu_item_id'])
                    ->where('outlet_id', $request->outlet_id)
                    ->firstOrFail();

                $unitPrice = $menuItem->base_price;
                $optionsSelected = [];

                if (!empty($itemInput['selected_option_ids'])) {
                    $options = MenuItemVariantOption::whereIn('id', $itemInput['selected_option_ids'])
                        ->where('is_available', true)
                        ->get();

                    foreach ($options as $opt) {
                        $unitPrice += $opt->price_modifier;
                        $optionsSelected[] = [
                            'variant_option_id' => $opt->id,
                            'option_name_snapshot' => $opt->name,
                            'price_modifier_snapshot' => $opt->price_modifier,
                        ];
                    }
                }

                $quantity = $itemInput['quantity'];
                $itemSubtotal = $unitPrice * $quantity;
                $subtotal += $itemSubtotal;

                $orderItemsData[] = [
                    'menu_item' => $menuItem,
                    'unit_price' => $unitPrice,
                    'quantity' => $quantity,
                    'subtotal' => $itemSubtotal,
                    'notes' => $itemInput['notes'] ?? null,
                    'options' => $optionsSelected,
                ];
            }

            $total = $subtotal;
            $isPaid = in_array($request->payment_method, ['cash', 'qris', 'card', 'debit', 'credit']);

            $newOrder = Order::create([
                'tenant_id' => $tenantId,
                'outlet_id' => $request->outlet_id,
                'table_session_id' => $tableSessionId,
                'table_id' => $request->table_id,
                'order_number' => $orderNumber,
                'customer_name' => $request->customer_name,
                'order_type' => $request->order_type,
                'status' => $isPaid ? 'confirmed' : 'pending_payment',
                'payment_status' => $isPaid ? 'paid' : 'unpaid',
                'total_amount' => $total,
                'discount_amount' => 0,
                'final_amount' => $total,
            ]);

            foreach ($orderItemsData as $itemData) {
                $orderItem = OrderItem::create([
                    'order_id' => $newOrder->id,
                    'menu_item_id' => $itemData['menu_item']->id,
                    'item_name_snapshot' => $itemData['menu_item']->name,
                    'base_price_snapshot' => $itemData['unit_price'],
                    'quantity' => $itemData['quantity'],
                    'subtotal' => $itemData['subtotal'],
                    'status' => 'pending',
                    'notes' => $itemData['notes'],
                ]);

                foreach ($itemData['options'] as $opt) {
                    OrderItemOption::create([
                        'order_item_id' => $orderItem->id,
                        'variant_option_id' => $opt['variant_option_id'],
                        'option_name_snapshot' => $opt['option_name_snapshot'],
                        'price_modifier_snapshot' => $opt['price_modifier_snapshot'],
                    ]);
                }
            }

            if ($isPaid) {
                Payment::create([
                    'order_id' => $newOrder->id,
                    'payment_method' => $request->payment_method,
                    'amount' => $total,
                    'status' => 'paid',
                    'paid_at' => now(),
                    'raw_payload' => [
                        'cash_received' => $request->cash_received ?? $total,
                        'change_amount' => max(0, ($request->cash_received ?? $total) - $total),
                        'cashier_id' => $user->id,
                    ],
                ]);
            }

            // Deduct ingredient stock
            $this->stockService->deductForOrder($newOrder);

            return $newOrder;
        });

        $order->load(['table', 'items.options', 'payments']);
        try {
            event(new OrderCreatedEvent($order));
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Broadcast OrderCreatedEvent: ' . $e->getMessage());
        }

        return response()->json([
            'message' => 'Pesanan kasir berhasil dibuat.',
            'order' => $order,
        ], 201);
    }

    public function updateStatus(Request $request, string $id): JsonResponse
    {
        $order = Order::with(['table', 'items.options', 'payments'])->findOrFail($id);

        $request->validate([
            'status' => ['required', 'string', 'in:pending_payment,confirmed,processing,preparing,ready,completed,cancelled,expired'],
        ]);

        $newStatus = $request->status;
        if ($newStatus === 'preparing') {
            $newStatus = 'processing';
        }

        DB::transaction(function () use ($order, $newStatus) {
            $order->status = $newStatus;

            if ($newStatus === 'ready') {
                $order->items()->where('is_voided', false)->where('status', '!=', 'served')->update(['status' => 'ready']);
            } elseif ($newStatus === 'processing') {
                $order->items()->where('is_voided', false)->where('status', 'pending')->update(['status' => 'cooking']);
            } elseif ($newStatus === 'completed') {
                $order->items()->where('is_voided', false)->update(['status' => 'served']);
                $order->payment_status = 'paid';
            } elseif ($newStatus === 'expired') {
                $order->payment_status = 'expired';
                foreach ($order->payments as $payment) {
                    if ($payment->status === 'pending') {
                        $payment->status = 'expired';
                        $payment->save();
                    }
                }
                $stockService = app(\App\Services\IngredientStockService::class);
                foreach ($order->items as $item) {
                    if (!$item->is_voided) {
                        $stockService->returnForVoidedItem($item);
                    }
                }
                if ($order->outlet_id) {
                    $stockService->syncMenuAvailability($order->outlet_id);
                }
            }

            $order->save();
        });

        try {
            event(new OrderStatusUpdatedEvent($order));
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Broadcast OrderStatusUpdatedEvent: ' . $e->getMessage());
        }

        return response()->json([
            'message' => 'Status pesanan berhasil diperbarui.',
            'order' => $order->fresh(['table', 'items.options', 'payments']),
        ]);
    }

    public function payCash(Request $request, string $id): JsonResponse
    {
        $order = Order::findOrFail($id);

        if ($order->payment_status === 'paid') {
            return response()->json(['message' => 'Pesanan ini sudah lunas dibayar.'], 422);
        }

        $request->validate([
            'cash_received' => ['required', 'integer', 'min:' . $order->final_amount],
        ]);

        $change = $request->cash_received - $order->final_amount;

        DB::transaction(function () use ($order, $request, $change) {
            Payment::create([
                'order_id' => $order->id,
                'payment_method' => 'cash',
                'amount' => $order->final_amount,
                'status' => 'paid',
                'paid_at' => now(),
                'raw_payload' => [
                    'cash_received' => $request->cash_received,
                    'change_amount' => $change,
                    'cashier_id' => $request->user()->id,
                ],
            ]);

            $order->payment_status = 'paid';
            if ($order->status === 'pending_payment') {
                $order->status = 'confirmed';
            }
            $order->save();
        });

        try {
            event(new OrderPaidEvent($order));
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Broadcast OrderPaidEvent: ' . $e->getMessage());
        }
        try {
            event(new OrderStatusUpdatedEvent($order));
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Broadcast OrderStatusUpdatedEvent: ' . $e->getMessage());
        }

        return response()->json([
            'message' => 'Pembayaran tunai berhasil dicatat.',
            'cash_received' => $request->cash_received,
            'change_amount' => $change,
            'order' => $order->fresh(['payments']),
        ]);
    }

    public function voidItem(Request $request, string $id): JsonResponse
    {
        $order = Order::with('items')->findOrFail($id);

        $request->validate([
            'order_item_id' => ['required', 'uuid', 'exists:order_items,id'],
            'void_reason' => ['required', 'string', 'max:255'],
        ]);

        $item = $order->items->firstWhere('id', $request->order_item_id);
        if (!$item) {
            return response()->json(['message' => 'Item tidak ditemukan pada pesanan ini.'], 404);
        }

        if ($item->is_voided) {
            return response()->json(['message' => 'Item sudah di-void sebelumnya.'], 422);
        }

        DB::transaction(function () use ($order, $item, $request) {
            $item->update([
                'is_voided' => true,
                'voided_by_user_id' => $request->user()->id,
                'voided_at' => now(),
            ]);

            $activeItems = $order->items()->where('is_voided', false)->get();
            $newTotal = $activeItems->sum('subtotal');

            $order->update([
                'total_amount' => $newTotal,
                'discount_amount' => 0,
                'final_amount' => $newTotal,
            ]);

            // Return ingredient stock for voided item
            $this->stockService->returnForVoidedItem($item);
        });

        return response()->json([
            'message' => 'Item berhasil di-void dan total pesanan diperbarui.',
            'order' => $order->fresh(['items']),
        ]);
    }

    public function closeTableSession(string $tableId): JsonResponse
    {
        $session = TableSession::where('table_id', $tableId)
            ->where('status', 'active')
            ->first();

        if (!$session) {
            return response()->json(['message' => 'Tidak ada sesi aktif pada meja ini.'], 404);
        }

        $session->update([
            'status' => 'closed',
            'closed_at' => now(),
        ]);

        return response()->json(['message' => 'Sesi meja berhasil ditutup. Meja sekarang kosong.']);
    }
}
