<?php

namespace App\Http\Controllers\Api;

use App\Events\OrderCreatedEvent;
use App\Http\Controllers\Controller;
use App\Models\MenuItem;
use App\Models\MenuItemVariantOption;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderItemOption;
use App\Models\Table;
use App\Models\TableSession;
use App\Services\IngredientStockService;
use App\Services\Payment\XenditOrderPaymentService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CustomerOrderController extends Controller
{
    public function __construct(
        protected XenditOrderPaymentService $paymentService,
        protected IngredientStockService $stockService,
    ) {
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'table_token' => ['required', 'string'],
            'customer_name' => ['required', 'string', 'max:100'],
            'customer_phone' => ['nullable', 'string', 'max:30'],
            'notes' => ['nullable', 'string', 'max:255'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.menu_item_id' => ['required', 'uuid'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.notes' => ['nullable', 'string', 'max:150'],
            'items.*.selected_option_ids' => ['nullable', 'array'],
            'items.*.selected_option_ids.*' => ['uuid'],
        ]);

        $token = $request->table_token;
        $table = Table::withoutGlobalScopes()
            ->with(['outlet.tenant'])
            ->where(function ($q) use ($token) {
                $q->where('qr_code_token', $token)
                  ->orWhere('table_number', $token)
                  ->orWhere('table_number', 'Meja ' . ltrim(str_ireplace('meja', '', $token), ' 0M'))
                  ->orWhere('table_number', 'Meja 0' . ltrim(str_ireplace('meja', '', $token), ' 0M'));
            })
            ->first();

        if (!$table) {
            $table = Table::withoutGlobalScopes()
                ->with(['outlet.tenant'])
                ->where('is_active', true)
                ->first();
        }

        if (!$table || !$table->is_active) {
            return response()->json(['message' => 'Meja tidak ditemukan atau tidak aktif.'], 404);
        }

        $tenant = $table->outlet->tenant;
        if (!$tenant || in_array($tenant->status, ['suspended', 'churned'])) {
            return response()->json(['message' => 'Restoran sedang tidak dapat menerima pesanan online.'], 403);
        }

        // 1. KEAMANAN: IDEMPOTENCY CHECK
        // Cek header Idempotency-Key atau generate fingerprint unik dari payload checkout
        $idempotencyKey = $request->header('Idempotency-Key') ?? $request->input('idempotency_key');
        if (empty($idempotencyKey)) {
            // Fingerprint otomatis berdasarkan meja, pelanggan, dan item yang dipesan
            $itemHash = md5(json_encode($request->items));
            $idempotencyKey = "AUTO-{$table->id}-{$request->customer_name}-{$itemHash}";
        }

        // Jika idempotency key sudah ada di database dalam 24 jam terakhir, kembalikan data yang sudah ada (Anti Duplikasi)
        $existingOrder = Order::withoutGlobalScopes()
            ->with(['payments'])
            ->where('idempotency_key', $idempotencyKey)
            ->where('created_at', '>=', now()->subHours(24))
            ->first();

        if ($existingOrder) {
            $existingPayment = $existingOrder->payments->first();
            return response()->json([
                'message' => 'Pesanan sudah pernah dibuat (Idempotent replay). Silakan selesaikan pembayaran.',
                'order' => [
                    'id' => $existingOrder->id,
                    'order_number' => $existingOrder->order_number,
                    'total_amount' => $existingOrder->final_amount,
                    'subtotal' => (int) $existingOrder->items->sum('subtotal'),
                    'service_fee' => 2000,
                    'tax_amount' => (int) round($existingOrder->items->sum('subtotal') * 0.10),
                    'discount_amount' => $existingOrder->discount_amount,
                    'status' => $existingOrder->status,
                    'payment_status' => $existingOrder->payment_status,
                    'created_at' => $existingOrder->created_at,
                ],
                'payment' => $existingPayment ? [
                    'id' => $existingPayment->id,
                    'payment_method' => $existingPayment->payment_method,
                    'qr_string' => $existingPayment->raw_payload['qr_string'] ?? null,
                    'account_number' => $existingPayment->raw_payload['account_number'] ?? null,
                    'bank_code' => $existingPayment->raw_payload['bank_code'] ?? null,
                    'expiration_date' => $existingPayment->raw_payload['expiration_date'] ?? null,
                    'amount' => $existingPayment->amount,
                ] : null,
            ], 200);
        }

        // 2. KEAMANAN: ATOMIC CACHE LOCK
        // Mencegah race-condition jika user mengklik tombol berkali-kali dalam selisih milidetik
        $lockKey = "checkout_lock:{$table->id}:" . md5($idempotencyKey);
        $lock = Cache::lock($lockKey, 10);

        if (!$lock->get()) {
            return response()->json([
                'message' => 'Pesanan sedang diproses. Mohon tunggu beberapa detik.',
            ], 429);
        }

        try {
        // Check ingredient availability before creating order
        $availabilityCheck = $this->stockService->checkAvailability($request->items, $table->outlet_id);
        if (!$availabilityCheck['available']) {
            return response()->json([
                'message' => 'Stok bahan tidak mencukupi untuk beberapa menu.',
                'errors' => $availabilityCheck['errors'],
            ], 422);
        }

        $result = DB::transaction(function () use ($request, $table, $tenant, $idempotencyKey) {
            // 1. Get or create active TableSession
            $session = TableSession::where('table_id', $table->id)
                ->where('status', 'active')
                ->first();

            if (!$session) {
                $session = TableSession::create([
                    'tenant_id' => $tenant->id,
                    'outlet_id' => $table->outlet_id,
                    'table_id' => $table->id,
                    'session_code' => 'SES-' . strtoupper(Str::random(8)),
                    'status' => 'active',
                    'opened_at' => now(),
                ]);
            }

            // 2. Generate unique order number: ORD-YYYYMMDD-XXXX
            $datePrefix = Carbon::now()->format('Ymd');
            $randomSuffix = strtoupper(Str::random(4));
            $orderNumber = "ORD-{$datePrefix}-{$randomSuffix}";

            // 3. Process items and calculate totals
            $subtotal = 0;
            $orderItemsData = [];

            foreach ($request->items as $itemInput) {
                $menuItem = MenuItem::withoutGlobalScopes()
                    ->where('id', $itemInput['menu_item_id'])
                    ->where(function ($q) use ($table) {
                        $q->where('outlet_id', $table->outlet_id)
                          ->orWhere('tenant_id', $table->tenant_id);
                    })
                    ->where('is_available', true)
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

            $serviceFee = $subtotal > 0 ? 2000 : 0;
            $tax = (int) round($subtotal * 0.10);
            $total = $subtotal + $serviceFee + $tax;

            $paymentMethod = $request->input('payment_method', 'qris');

            $order = Order::create([
                'idempotency_key' => $idempotencyKey,
                'tenant_id' => $tenant->id,
                'outlet_id' => $table->outlet_id,
                'table_session_id' => $session->id,
                'table_id' => $table->id,
                'order_number' => $orderNumber,
                'customer_name' => $request->customer_name,
                'order_type' => 'dine_in',
                'status' => 'pending_payment',
                'payment_status' => 'unpaid',
                'total_amount' => $total,
                'discount_amount' => 0,
                'final_amount' => $total,
                'notes' => $request->notes,
            ]);

            foreach ($orderItemsData as $itemData) {
                $orderItem = OrderItem::create([
                    'order_id' => $order->id,
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

            // Deduct ingredient stock
            $this->stockService->deductForOrder($order);

            // 4. Create payment charge (QRIS or Cash)
            $paymentMethod = $request->input('payment_method', 'qris');
            if ($paymentMethod === 'cash') {
                $payment = \App\Models\Payment::create([
                    'order_id' => $order->id,
                    'xendit_transaction_id' => 'CASH-' . strtoupper(Str::random(12)),
                    'payment_method' => 'cash',
                    'amount' => $order->final_amount,
                    'status' => 'pending',
                    'raw_payload' => [
                        'reference_id' => 'CASH-' . strtoupper(Str::random(8)),
                        'instructions' => 'Silakan lakukan pembayaran ke kasir.',
                    ],
                ]);
            } else {
                $payment = $this->paymentService->createPaymentCharge($order, $paymentMethod);
            }

            return ['order' => $order, 'payment' => $payment];
        });

        event(new OrderCreatedEvent($result['order']));

        return response()->json([
            'message' => 'Pesanan berhasil dibuat. Silakan selesaikan pembayaran.',
            'order' => [
                'id' => $result['order']->id,
                'order_number' => $result['order']->order_number,
                'total_amount' => $result['order']->final_amount,
                'subtotal' => (int) ($result['order']->items->sum('subtotal') ?: 0),
                'service_fee' => 2000,
                'tax_amount' => (int) round((($result['order']->items->sum('subtotal') ?: 0)) * 0.10),
                'discount_amount' => $result['order']->discount_amount,
                'status' => $result['order']->status,
                'payment_status' => $result['order']->payment_status,
                'created_at' => $result['order']->created_at,
            ],
            'payment' => [
                'id' => $result['payment']->id,
                'payment_method' => $result['payment']->payment_method,
                'qr_string' => $result['payment']->raw_payload['qr_string'] ?? null,
                'account_number' => $result['payment']->raw_payload['account_number'] ?? null,
                'bank_code' => $result['payment']->raw_payload['bank_code'] ?? null,
                'expiration_date' => $result['payment']->raw_payload['expiration_date'] ?? null,
                'amount' => $result['payment']->amount,
            ],
        ], 201);
        } finally {
            $lock->release();
        }
    }

    public function activeOrder(Request $request): JsonResponse
    {
        $tableToken = $request->query('table_token') ?? $request->query('table_code');
        $outletId = $request->query('outlet_id');

        if (!$tableToken) {
            return response()->json(['order' => null]);
        }

        // Cari meja berdasarkan token atau nomor meja
        $table = Table::withoutGlobalScopes()
            ->where(function ($q) use ($tableToken) {
                $q->where('qr_code_token', $tableToken)
                  ->orWhere('table_number', $tableToken)
                  ->orWhere('table_number', 'Meja ' . ltrim(str_ireplace('meja', '', $tableToken), ' 0M'))
                  ->orWhere('table_number', 'Meja 0' . ltrim(str_ireplace('meja', '', $tableToken), ' 0M'));
            })
            ->when(\Illuminate\Support\Str::isUuid($outletId), fn($q) => $q->where('outlet_id', $outletId))
            ->first();

        if (!$table) {
            return response()->json(['order' => null]);
        }

        // Cari semua pesanan aktif untuk meja ini yang sudah diterima dapur (tidak ada pending_payment, cancelled, atau expired)
        $orders = Order::withoutGlobalScopes()
            ->with(['payments', 'items.options', 'table'])
            ->where('table_id', $table->id)
            ->whereIn('status', ['confirmed', 'processing', 'preparing', 'cooking', 'ready', 'completed'])
            ->where('payment_status', 'paid')
            ->whereNotIn('status', ['cancelled', 'expired', 'pending_payment'])
            ->where('created_at', '>=', now()->subHours(6))
            ->oldest('created_at')
            ->get();

        if ($orders->isEmpty()) {
            return response()->json(['order' => null, 'orders' => []]);
        }

        $formattedOrders = $orders->map(function ($ord) {
            $itemsSubtotal = (int) $ord->items->sum('subtotal');
            $serviceFee = $itemsSubtotal > 0 ? 2000 : 0;
            $tax = (int) round($itemsSubtotal * 0.10);

            return [
                'id' => $ord->id,
                'order_number' => $ord->order_number,
                'customer_name' => $ord->customer_name,
                'status' => $ord->status,
                'payment_status' => $ord->payment_status,
                'total_amount' => $ord->final_amount,
                'subtotal' => $itemsSubtotal,
                'service_fee' => $serviceFee,
                'tax_amount' => $tax,
                'discount_amount' => $ord->discount_amount,
                'table_id' => $ord->table_id,
                'table' => $ord->table ? ['id' => $ord->table->id, 'table_number' => $ord->table->table_number] : null,
                'items' => $ord->items,
                'payments' => $ord->payments,
                'created_at' => $ord->created_at,
            ];
        });

        return response()->json([
            'order' => $formattedOrders->last(),
            'orders' => $formattedOrders,
        ]);
    }

    public function status(string $id): JsonResponse
    {
        $order = Order::withoutGlobalScopes()
            ->with(['payments', 'items.options', 'table'])
            ->findOrFail($id);

        // Auto-expire pending payment if expiration_date has passed
        $pendingPayment = $order->payments->firstWhere('status', 'pending');
        if ($pendingPayment && in_array($order->payment_status, ['unpaid', 'pending'])) {
            $expirationDate = $pendingPayment->raw_payload['expiration_date'] ?? null;
            if ($expirationDate && now()->isAfter($expirationDate)) {
                $pendingPayment->status = 'expired';
                $pendingPayment->save();

                $order->payment_status = 'expired';
                if ($order->status === 'pending_payment') {
                    $order->status = 'expired';
                }
                $order->save();

                foreach ($order->items as $item) {
                    if (!$item->is_voided) {
                        $this->stockService->returnForVoidedItem($item);
                    }
                }
                if ($order->outlet_id) {
                    $this->stockService->syncMenuAvailability($order->outlet_id);
                }

                event(new \App\Events\OrderStatusUpdatedEvent($order));
            }
        }

        $itemsSubtotal = (int) $order->items->sum('subtotal');
        $serviceFee = $itemsSubtotal > 0 ? 2000 : 0;
        $tax = (int) round($itemsSubtotal * 0.10);

        return response()->json([
            'order' => [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'customer_name' => $order->customer_name,
                'status' => $order->status,
                'payment_status' => $order->payment_status,
                'total_amount' => $order->final_amount,
                'subtotal' => $itemsSubtotal,
                'service_fee' => $serviceFee,
                'tax_amount' => $tax,
                'discount_amount' => $order->discount_amount,
                'table' => $order->table ? ['table_number' => $order->table->table_number] : null,
                'items' => $order->items,
                'payments' => $order->payments,
                'created_at' => $order->created_at,
            ],
        ]);
    }
    public function simulatePay(string $id, \App\Services\Payment\XenditOrderPaymentService $paymentService): JsonResponse
    {
        $order = Order::withoutGlobalScopes()->with('payments')->findOrFail($id);

        $pendingPayment = $order->payments->firstWhere('status', 'pending');
        if (!$pendingPayment) {
            return response()->json(['message' => 'Tidak ada pembayaran tertunda yang dapat disimulasikan.'], 400);
        }

        $expirationDate = $pendingPayment->raw_payload['expiration_date'] ?? null;
        if ($expirationDate && now()->isAfter($expirationDate)) {
            $pendingPayment->status = 'expired';
            $pendingPayment->save();

            $order->payment_status = 'expired';
            if ($order->status === 'pending_payment') {
                $order->status = 'cancelled';
            }
            $order->save();
            event(new \App\Events\OrderStatusUpdatedEvent($order));
            return response()->json(['message' => 'Nomor Virtual Account telah kadaluarsa / hangus.'], 422);
        }

        $xenditResult = null;

        if ($pendingPayment) {
            $xenditResult = $paymentService->simulatePayment($pendingPayment);
        }

        DB::transaction(function () use ($order) {
            $order->payment_status = 'paid';
            if ($order->status === 'pending_payment') {
                $order->status = 'confirmed';
            }
            $order->save();

            foreach ($order->payments as $payment) {
                if ($payment->status === 'pending') {
                    $payment->status = 'paid';
                    $payment->paid_at = now();
                    $payment->save();
                }
            }
        });

        event(new \App\Events\OrderPaidEvent($order));
        event(new \App\Events\OrderStatusUpdatedEvent($order));

        return response()->json([
            'message' => 'Pembayaran sandbox berhasil disimulasikan.',
            'xendit_simulated' => $xenditResult['success'] ?? false,
            'xendit_details' => $xenditResult['data'] ?? null,
            'order' => [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'status' => $order->status,
                'payment_status' => $order->payment_status,
            ],
        ]);
    }
        public function expire(string $id): JsonResponse
    {
        $order = Order::withoutGlobalScopes()->with(['payments', 'items.menuItem.recipes.ingredient'])->findOrFail($id);

        if (!in_array($order->status, ['pending_payment', 'pending']) || !in_array($order->payment_status, ['unpaid', 'pending'])) {
            return response()->json([
                'message' => 'Pesanan tidak dalam status menunggu pembayaran.',
                'order' => [
                    'id' => $order->id,
                    'order_number' => $order->order_number,
                    'status' => $order->status,
                    'payment_status' => $order->payment_status,
                ],
            ], 200);
        }

        DB::transaction(function () use ($order) {
            $order->status = 'expired';
            $order->payment_status = 'expired';
            $order->save();

            foreach ($order->payments as $payment) {
                if ($payment->status === 'pending') {
                    $payment->status = 'expired';
                    $payment->save();
                }
            }

            // Return ingredient stock that was deducted when order was created
            foreach ($order->items as $item) {
                if (!$item->is_voided) {
                    $this->stockService->returnForVoidedItem($item);
                }
            }

            if ($order->outlet_id) {
                $this->stockService->syncMenuAvailability($order->outlet_id);
            }
        });

        try {
            event(new \App\Events\OrderStatusUpdatedEvent($order));
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Broadcast OrderStatusUpdatedEvent on expire: ' . $e->getMessage());
        }

        return response()->json([
            'message' => 'Pesanan telah dinyatakan kadaluarsa karena batas waktu pembayaran habis.',
            'order' => [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'status' => $order->status,
                'payment_status' => $order->payment_status,
            ],
        ]);
    }

    public function cancel(string $id): JsonResponse
    {
        $order = Order::withoutGlobalScopes()->with(['payments', 'items.menuItem.recipes.ingredient'])->findOrFail($id);

        if (!in_array($order->status, ['pending_payment', 'pending']) || !in_array($order->payment_status, ['unpaid', 'pending'])) {
            return response()->json([
                'message' => 'Pesanan tidak dapat dibatalkan karena sudah diproses atau telah selesai.',
            ], 422);
        }

        DB::transaction(function () use ($order) {
            $order->status = 'cancelled';
            $order->payment_status = 'cancelled';
            $order->save();

            foreach ($order->payments as $payment) {
                if ($payment->status === 'pending') {
                    $payment->status = 'cancelled';
                    $payment->save();
                }
            }

            // Return ingredient stock that was deducted when order was created
            foreach ($order->items as $item) {
                if (!$item->is_voided) {
                    $this->stockService->returnForVoidedItem($item);
                }
            }

            if ($order->outlet_id) {
                $this->stockService->syncMenuAvailability($order->outlet_id);
            }
        });

        try {
            event(new \App\Events\OrderStatusUpdatedEvent($order));
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Broadcast OrderStatusUpdatedEvent on cancel: ' . $e->getMessage());
        }

        return response()->json([
            'message' => 'Pesanan dan transfer pembayaran berhasil dibatalkan.',
            'order' => [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'status' => $order->status,
                'payment_status' => $order->payment_status,
            ],
        ]);
    }
}
