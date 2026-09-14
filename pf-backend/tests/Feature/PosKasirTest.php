<?php

use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Outlet;
use App\Models\Table;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);

    $this->tenant = Tenant::create([
        'name' => 'Resto POS Test',
        'subdomain' => 'resto-pos',
        'status' => 'active',
        'trial_ends_at' => now()->addDays(14),
    ]);

    $this->outlet = Outlet::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Cabang Kasir Utama',
    ]);

    $this->kasir = User::create([
        'tenant_id' => $this->tenant->id,
        'outlet_id' => $this->outlet->id,
        'name' => 'Kasir Siti',
        'email' => 'kasir@restopos.test',
        'password' => 'secret123',
    ]);
    $this->kasir->assignRole('kasir');
    $this->token = $this->kasir->createToken('kasir-token')->plainTextToken;

    $this->table = Table::create([
        'tenant_id' => $this->tenant->id,
        'outlet_id' => $this->outlet->id,
        'table_number' => 'Meja 05',
        'qr_code_token' => 'qr_token_meja_05',
        'is_active' => true,
    ]);

    $this->category = MenuCategory::create([
        'tenant_id' => $this->tenant->id,
        'outlet_id' => $this->outlet->id,
        'name' => 'Makanan Kasir',
    ]);

    $this->itemA = MenuItem::create([
        'tenant_id' => $this->tenant->id,
        'outlet_id' => $this->outlet->id,
        'category_id' => $this->category->id,
        'name' => 'Ayam Bakar Madu',
        'base_price' => 30000,
        'is_available' => true,
    ]);

    $this->itemB = MenuItem::create([
        'tenant_id' => $this->tenant->id,
        'outlet_id' => $this->outlet->id,
        'category_id' => $this->category->id,
        'name' => 'Es Teh Manis',
        'base_price' => 5000,
        'is_available' => true,
    ]);
});

test('kasir can create order directly with cash payment and get change', function () {
    // 1x Ayam Bakar (30k) + 2x Es Teh (10k) = 40k + 10% tax (4k) = 44k
    // Cash received: 50k, change: 6k
    $response = $this->withToken($this->token)->postJson('/api/pos/orders', [
        'outlet_id' => $this->outlet->id,
        'order_type' => 'dine_in',
        'table_id' => $this->table->id,
        'customer_name' => 'Pak Joko',
        'payment_method' => 'cash',
        'cash_received' => 50000,
        'items' => [
            ['menu_item_id' => $this->itemA->id, 'quantity' => 1],
            ['menu_item_id' => $this->itemB->id, 'quantity' => 2],
        ],
    ]);

    $response->assertStatus(201)
        ->assertJsonPath('order.total_amount', 44000)
        ->assertJsonPath('order.payment_status', 'paid')
        ->assertJsonPath('order.status', 'processing');

    $orderId = $response->json('order.id');
    $order = Order::find($orderId);
    expect($order->payments()->count())->toBe(1)
        ->and($order->payments()->first()->raw_payload['change_amount'])->toBe(6000);
});

test('kasir can pay cash for existing unpaid order', function () {
    // Create unpaid order first
    $orderResponse = $this->withToken($this->token)->postJson('/api/pos/orders', [
        'outlet_id' => $this->outlet->id,
        'order_type' => 'takeaway',
        'customer_name' => 'Ibu Ratna',
        'items' => [
            ['menu_item_id' => $this->itemA->id, 'quantity' => 1],
        ],
    ]);

    $orderId = $orderResponse->json('order.id');
    $totalAmount = $orderResponse->json('order.total_amount'); // 30k + 10% = 33k

    // Pay cash
    $payResponse = $this->withToken($this->token)->postJson("/api/pos/orders/{$orderId}/pay-cash", [
        'cash_received' => 50000,
    ]);

    $payResponse->assertOk()
        ->assertJsonPath('change_amount', 50000 - $totalAmount)
        ->assertJsonPath('order.payment_status', 'paid');
});

test('kasir can void an item and close table session', function () {
    $orderResponse = $this->withToken($this->token)->postJson('/api/pos/orders', [
        'outlet_id' => $this->outlet->id,
        'order_type' => 'dine_in',
        'table_id' => $this->table->id,
        'customer_name' => 'Mas Eko',
        'items' => [
            ['menu_item_id' => $this->itemA->id, 'quantity' => 1],
            ['menu_item_id' => $this->itemB->id, 'quantity' => 1],
        ],
    ]);

    $orderId = $orderResponse->json('order.id');
    $order = Order::with('items')->find($orderId);
    $itemToVoid = $order->items->firstWhere('menu_item_id', $this->itemB->id);

    // 1. Void Item B (Es Teh Manis)
    $voidResponse = $this->withToken($this->token)->postJson("/api/pos/orders/{$orderId}/void-item", [
        'order_item_id' => $itemToVoid->id,
        'void_reason' => 'Pelanggan membatalkan pesanan minuman',
    ]);

    $voidResponse->assertOk()
        ->assertJsonPath('order.total_amount', 33000)
        ->assertJsonPath('order.final_amount', 33000);

    // 2. Close Table Session
    $closeResponse = $this->withToken($this->token)->postJson("/api/pos/tables/{$this->table->id}/close-session");
    $closeResponse->assertOk();
});
