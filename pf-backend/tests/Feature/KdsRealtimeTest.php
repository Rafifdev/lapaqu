<?php

use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderItem;
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
        'name' => 'Resto KDS Test',
        'subdomain' => 'resto-kds',
        'status' => 'active',
        'trial_ends_at' => now()->addDays(14),
    ]);

    $this->outlet = Outlet::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Cabang Kitchen',
    ]);

    $this->kitchenStaff = User::create([
        'tenant_id' => $this->tenant->id,
        'outlet_id' => $this->outlet->id,
        'name' => 'Chef Arnold',
        'email' => 'chef@restokds.test',
        'password' => 'secret123',
    ]);
    $this->kitchenStaff->assignRole('kitchen_staff');
    $this->token = $this->kitchenStaff->createToken('kitchen-token')->plainTextToken;

    $this->table = Table::create([
        'tenant_id' => $this->tenant->id,
        'outlet_id' => $this->outlet->id,
        'table_number' => 'Meja KDS',
        'qr_code_token' => 'qr_token_kds_test',
        'is_active' => true,
    ]);

    $this->category = MenuCategory::create([
        'tenant_id' => $this->tenant->id,
        'outlet_id' => $this->outlet->id,
        'name' => 'Kitchen Category',
    ]);

    $this->menuItem = MenuItem::create([
        'tenant_id' => $this->tenant->id,
        'outlet_id' => $this->outlet->id,
        'category_id' => $this->category->id,
        'name' => 'Sop Buntut Spesial',
        'base_price' => 50000,
        'is_available' => true,
    ]);
});

test('kitchen staff can view active KDS queue and update item status', function () {
    $order = Order::create([
        'tenant_id' => $this->tenant->id,
        'outlet_id' => $this->outlet->id,
        'table_id' => $this->table->id,
        'order_number' => 'KDS-ORD-001',
        'customer_name' => 'Tamu Kitchen',
        'order_type' => 'dine_in',
        'status' => 'processing',
        'payment_status' => 'paid',
        'total_amount' => 55000,
        'discount_amount' => 0,
        'final_amount' => 55000,
    ]);

    $orderItem = OrderItem::create([
        'order_id' => $order->id,
        'menu_item_id' => $this->menuItem->id,
        'item_name_snapshot' => 'Sop Buntut Spesial',
        'base_price_snapshot' => 50000,
        'quantity' => 1,
        'subtotal' => 50000,
        'status' => 'pending',
    ]);

    // 1. Kitchen lists active queue
    $kdsOrders = $this->withToken($this->token)->getJson('/api/kds/orders');
    $kdsOrders->assertOk()
        ->assertJsonCount(1, 'orders')
        ->assertJsonPath('orders.0.order_number', 'KDS-ORD-001');

    // 2. Update to cooking
    $cookingRes = $this->withToken($this->token)->patchJson("/api/kds/items/{$orderItem->id}/status", [
        'status' => 'cooking',
    ]);
    $cookingRes->assertOk()
        ->assertJsonPath('item.status', 'cooking');

    // 3. Update to ready
    $readyRes = $this->withToken($this->token)->patchJson("/api/kds/items/{$orderItem->id}/status", [
        'status' => 'ready',
    ]);
    $readyRes->assertOk()
        ->assertJsonPath('item.status', 'ready');

    $order->refresh();
    expect($order->status)->toBe('ready');

    // 4. Update to served (order automatically completed)
    $servedRes = $this->withToken($this->token)->patchJson("/api/kds/items/{$orderItem->id}/status", [
        'status' => 'served',
    ]);
    $servedRes->assertOk()
        ->assertJsonPath('item.status', 'served');

    $order->refresh();
    expect($order->status)->toBe('completed');
});
