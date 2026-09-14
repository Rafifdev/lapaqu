<?php

use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Outlet;
use App\Models\Payment;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);

    $this->tenant = Tenant::create([
        'name' => 'Resto Report Test',
        'subdomain' => 'resto-report',
        'status' => 'active',
        'trial_ends_at' => now()->addDays(14),
    ]);

    $this->outlet = Outlet::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Cabang Laporan',
    ]);

    $this->owner = User::create([
        'tenant_id' => $this->tenant->id,
        'outlet_id' => $this->outlet->id,
        'name' => 'Owner Report',
        'email' => 'owner@report.test',
        'password' => 'secret123',
    ]);
    $this->owner->assignRole('owner');
    $this->token = $this->owner->createToken('report-token')->plainTextToken;

    $this->category = MenuCategory::create([
        'tenant_id' => $this->tenant->id,
        'outlet_id' => $this->outlet->id,
        'name' => 'Menu Report',
    ]);

    $this->item1 = MenuItem::create([
        'tenant_id' => $this->tenant->id,
        'outlet_id' => $this->outlet->id,
        'category_id' => $this->category->id,
        'name' => 'Mie Goreng Seafood',
        'base_price' => 35000,
        'is_available' => true,
    ]);

    $this->item2 = MenuItem::create([
        'tenant_id' => $this->tenant->id,
        'outlet_id' => $this->outlet->id,
        'category_id' => $this->category->id,
        'name' => 'Jus Alpukat',
        'base_price' => 15000,
        'is_available' => true,
    ]);

    // Seed Order 1 (Cash: 50k)
    $order1 = Order::create([
        'tenant_id' => $this->tenant->id,
        'outlet_id' => $this->outlet->id,
        'order_number' => 'REP-001',
        'customer_name' => 'Pelanggan 1',
        'order_type' => 'dine_in',
        'status' => 'completed',
        'payment_status' => 'paid',
        'total_amount' => 50000,
        'discount_amount' => 0,
        'final_amount' => 50000,
        'created_at' => now(),
    ]);
    OrderItem::create([
        'order_id' => $order1->id,
        'menu_item_id' => $this->item1->id,
        'item_name_snapshot' => 'Mie Goreng Seafood',
        'base_price_snapshot' => 35000,
        'quantity' => 1,
        'subtotal' => 35000,
        'status' => 'served',
    ]);
    OrderItem::create([
        'order_id' => $order1->id,
        'menu_item_id' => $this->item2->id,
        'item_name_snapshot' => 'Jus Alpukat',
        'base_price_snapshot' => 15000,
        'quantity' => 1,
        'subtotal' => 15000,
        'status' => 'served',
    ]);
    Payment::create([
        'order_id' => $order1->id,
        'payment_method' => 'cash',
        'amount' => 50000,
        'status' => 'paid',
        'paid_at' => now(),
    ]);

    // Seed Order 2 (QRIS: 35k)
    $order2 = Order::create([
        'tenant_id' => $this->tenant->id,
        'outlet_id' => $this->outlet->id,
        'order_number' => 'REP-002',
        'customer_name' => 'Pelanggan 2',
        'order_type' => 'takeaway',
        'status' => 'completed',
        'payment_status' => 'paid',
        'total_amount' => 35000,
        'discount_amount' => 0,
        'final_amount' => 35000,
        'created_at' => now(),
    ]);
    OrderItem::create([
        'order_id' => $order2->id,
        'menu_item_id' => $this->item1->id,
        'item_name_snapshot' => 'Mie Goreng Seafood',
        'base_price_snapshot' => 35000,
        'quantity' => 1,
        'subtotal' => 35000,
        'status' => 'served',
    ]);
    Payment::create([
        'order_id' => $order2->id,
        'payment_method' => 'qris',
        'amount' => 35000,
        'status' => 'paid',
        'paid_at' => now(),
    ]);
});

test('owner can view sales summary with payment breakdown', function () {
    $response = $this->withToken($this->token)->getJson('/api/reports/sales-summary');

    $response->assertOk()
        ->assertJsonPath('summary.gross_sales', 85000)
        ->assertJsonPath('summary.net_sales', 85000)
        ->assertJsonPath('summary.total_orders', 2)
        ->assertJsonPath('summary.average_order_value', 42500)
        ->assertJsonCount(2, 'payment_breakdown');
});

test('owner can view top items ranking and export CSV', function () {
    // 1. Top Items
    $topResponse = $this->withToken($this->token)->getJson('/api/reports/top-items');
    $topResponse->assertOk()
        ->assertJsonCount(2, 'top_items')
        ->assertJsonPath('top_items.0.item_name', 'Mie Goreng Seafood')
        ->assertJsonPath('top_items.0.total_quantity', 2);

    // 2. Hourly Sales
    $hourlyResponse = $this->withToken($this->token)->getJson('/api/reports/hourly-sales');
    $hourlyResponse->assertOk()
        ->assertJsonCount(24, 'hourly_distribution');

    // 3. Export CSV
    $csvResponse = $this->withToken($this->token)->get('/api/reports/export-csv');
    $csvResponse->assertOk()
        ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
});
