<?php

use App\Models\MenuCategory;
use App\Models\MenuItem;
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
        'name' => 'Resto QR Test',
        'subdomain' => 'resto-qr',
        'status' => 'active',
        'trial_ends_at' => now()->addDays(14),
    ]);

    $this->outlet = Outlet::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Cabang Sudirman',
    ]);

    $this->owner = User::create([
        'tenant_id' => $this->tenant->id,
        'outlet_id' => $this->outlet->id,
        'name' => 'Owner QR',
        'email' => 'owner@restoqr.test',
        'password' => 'secret123',
    ]);
    $this->owner->assignRole('owner');

    $this->token = $this->owner->createToken('test')->plainTextToken;
});

test('owner can manage tables and regenerate QR tokens', function () {
    // 1. Create table
    $createResponse = $this->withToken($this->token)->postJson('/api/tables', [
        'outlet_id' => $this->outlet->id,
        'table_number' => 'Meja 01',
        'capacity' => 4,
    ]);

    $createResponse->assertStatus(201)
        ->assertJsonPath('table.table_number', 'Meja 01');

    $tableId = $createResponse->json('table.id');
    $oldQrToken = $createResponse->json('table.qr_code_token');

    // 2. Duplicate table number rejected
    $this->withToken($this->token)->postJson('/api/tables', [
        'outlet_id' => $this->outlet->id,
        'table_number' => 'Meja 01',
    ])->assertStatus(422);

    // 3. Regenerate QR Token
    $regenResponse = $this->withToken($this->token)->postJson("/api/tables/{$tableId}/regenerate-qr");
    $regenResponse->assertOk();

    $newQrToken = $regenResponse->json('table.qr_code_token');
    expect($newQrToken)->not->toBe($oldQrToken);

    // 4. Download SVG QR Code
    $svgResponse = $this->withToken($this->token)->get("/api/tables/{$tableId}/qr-code");
    $svgResponse->assertOk()
        ->assertHeader('Content-Type', 'image/svg+xml');
});

test('public customer can resolve table and view menu catalog via QR token', function () {
    $table = Table::create([
        'tenant_id' => $this->tenant->id,
        'outlet_id' => $this->outlet->id,
        'table_number' => 'VIP 01',
        'qr_code_token' => 'cust_qr_token_abc_123',
        'is_active' => true,
    ]);

    $category = MenuCategory::create([
        'tenant_id' => $this->tenant->id,
        'outlet_id' => $this->outlet->id,
        'name' => 'Signature Coffee',
    ]);

    MenuItem::create([
        'tenant_id' => $this->tenant->id,
        'outlet_id' => $this->outlet->id,
        'category_id' => $category->id,
        'name' => 'Caramel Macchiato',
        'base_price' => 28000,
        'is_available' => true,
    ]);

    $response = $this->getJson('/api/public/tables/cust_qr_token_abc_123');

    $response->assertOk()
        ->assertJsonPath('table.table_number', 'VIP 01')
        ->assertJsonPath('outlet.name', 'Cabang Sudirman')
        ->assertJsonPath('tenant.subdomain', 'resto-qr')
        ->assertJsonCount(1, 'categories')
        ->assertJsonCount(1, 'menu_items')
        ->assertJsonPath('menu_items.0.name', 'Caramel Macchiato');
});
