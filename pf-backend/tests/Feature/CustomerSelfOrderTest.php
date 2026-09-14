<?php

use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\MenuItemVariantGroup;
use App\Models\MenuItemVariantOption;
use App\Models\Outlet;
use App\Models\Payment;
use App\Models\SettlementLog;
use App\Models\Table;
use App\Models\Tenant;
use App\Models\TenantPaymentAccount;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);

    $this->tenant = Tenant::create([
        'name' => 'Resto Self Order',
        'subdomain' => 'resto-selforder',
        'status' => 'active',
        'trial_ends_at' => now()->addDays(14),
    ]);

    $this->outlet = Outlet::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Cabang Self Order',
    ]);

    $this->table = Table::create([
        'tenant_id' => $this->tenant->id,
        'outlet_id' => $this->outlet->id,
        'table_number' => 'T-10',
        'qr_code_token' => 'qr_token_self_order_123',
        'is_active' => true,
    ]);

    $this->paymentAccount = TenantPaymentAccount::create([
        'tenant_id' => $this->tenant->id,
        'xendit_sub_account_id' => 'xnd_sub_test_123',
        'bank_code' => 'BCA',
        'bank_account_number' => '1234567890',
        'bank_account_holder_name' => 'Resto Self Order',
        'is_active' => true,
    ]);

    $this->category = MenuCategory::create([
        'tenant_id' => $this->tenant->id,
        'outlet_id' => $this->outlet->id,
        'name' => 'Makanan',
    ]);

    $this->menuItem = MenuItem::create([
        'tenant_id' => $this->tenant->id,
        'outlet_id' => $this->outlet->id,
        'category_id' => $this->category->id,
        'name' => 'Nasi Goreng Spesial',
        'base_price' => 25000,
        'is_available' => true,
    ]);

    $this->variantGroup = MenuItemVariantGroup::create([
        'menu_item_id' => $this->menuItem->id,
        'name' => 'Level Pedas',
        'is_required' => true,
        'min_selection' => 1,
        'max_selection' => 1,
    ]);

    $this->optionPedas = MenuItemVariantOption::create([
        'variant_group_id' => $this->variantGroup->id,
        'name' => 'Pedas Gila (+2k)',
        'price_modifier' => 2000,
        'is_available' => true,
    ]);
});

test('customer can create self-order via table QR token and poll status', function () {
    // 1. Customer places order: 2x Nasi Goreng (25k + 2k) = 54k + 10% tax (5.4k) = 59.4k
    $response = $this->postJson('/api/public/orders', [
        'table_token' => 'qr_token_self_order_123',
        'customer_name' => 'Budi Pelanggan',
        'customer_phone' => '08123456789',
        'notes' => 'Jangan pakai daun bawang',
        'items' => [
            [
                'menu_item_id' => $this->menuItem->id,
                'quantity' => 2,
                'notes' => 'Telor ceplok matang',
                'selected_option_ids' => [$this->optionPedas->id],
            ],
        ],
    ]);

    $response->assertStatus(201)
        ->assertJsonStructure([
            'message',
            'order' => ['id', 'order_number', 'total_amount', 'status', 'payment_status'],
            'payment' => ['id', 'payment_method', 'qr_string', 'amount'],
        ]);

    $orderId = $response->json('order.id');
    $totalAmount = $response->json('order.total_amount');
    expect($totalAmount)->toBe(59400); // (27000 * 2) + 10% tax = 54000 + 5400

    // 2. Customer polls order status
    $statusResponse = $this->getJson("/api/public/orders/{$orderId}/status");
    $statusResponse->assertOk()
        ->assertJsonPath('order.customer_name', 'Budi Pelanggan')
        ->assertJsonPath('order.status', 'pending_payment')
        ->assertJsonPath('order.payment_status', 'unpaid');
});

test('order payment webhook updates status and logs settlement fee', function () {
    config(['services.xendit.callback_token' => 'order_callback_secret']);

    // Place order first
    $orderResponse = $this->postJson('/api/public/orders', [
        'table_token' => 'qr_token_self_order_123',
        'customer_name' => 'Andi Santoso',
        'items' => [
            [
                'menu_item_id' => $this->menuItem->id,
                'quantity' => 1,
            ],
        ],
    ]);

    $orderId = $orderResponse->json('order.id');
    $paymentId = $orderResponse->json('payment.id');
    $payment = Payment::find($paymentId);

    // Webhook callback simulates Xendit payment notification
    $webhookResponse = $this->withHeader('x-callback-token', 'order_callback_secret')
        ->postJson('/api/webhooks/xendit/order-payment', [
            'id' => $payment->xendit_transaction_id,
            'status' => 'COMPLETED',
            'amount' => $payment->amount,
        ]);

    $webhookResponse->assertOk()
        ->assertJson(['status' => 'paid']);

    $payment->refresh();
    expect($payment->status)->toBe('paid')
        ->and($payment->order->payment_status)->toBe('paid')
        ->and($payment->order->status)->toBe('processing');

    // Verify SettlementLog record created
    $log = SettlementLog::where('order_id', $payment->order_id)->first();
    expect($log)->not->toBeNull()
        ->and($log->status)->toBe('settled')
        ->and($log->platform_fee)->toBe((int) round($payment->amount * 0.007))
        ->and($log->net_amount)->toBe($payment->amount - $log->platform_fee);
});
