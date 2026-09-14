<?php

use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Outlet;
use App\Models\Plan;
use App\Models\Table;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->plan = Plan::create([
        'code' => 'basic',
        'name' => 'Basic Plan',
        'price_per_outlet_monthly' => 99000,
        'max_tables_per_outlet' => 10,
        'max_users_per_outlet' => 3,
        'max_outlets' => 1,
        'is_active' => true,
    ]);

    $this->tenantA = Tenant::create([
        'name' => 'Resto A',
        'subdomain' => 'restoa',
        'status' => 'active',
        'trial_ends_at' => now()->addDays(14),
    ]);

    $this->tenantB = Tenant::create([
        'name' => 'Resto B',
        'subdomain' => 'restob',
        'status' => 'active',
        'trial_ends_at' => now()->addDays(14),
    ]);

    $this->outletA = Outlet::create([
        'tenant_id' => $this->tenantA->id,
        'name' => 'Outlet Resto A',
        'timezone' => 'Asia/Jakarta',
    ]);

    $this->outletB = Outlet::create([
        'tenant_id' => $this->tenantB->id,
        'name' => 'Outlet Resto B',
        'timezone' => 'Asia/Jakarta',
    ]);
});

test('tenant scope automatically isolates data between tenants', function () {
    // Create tables for Tenant A and Tenant B
    Table::create([
        'tenant_id' => $this->tenantA->id,
        'outlet_id' => $this->outletA->id,
        'table_number' => 'T1',
        'qr_code_token' => 'qr_token_a_1',
    ]);

    Table::create([
        'tenant_id' => $this->tenantB->id,
        'outlet_id' => $this->outletB->id,
        'table_number' => 'T1',
        'qr_code_token' => 'qr_token_b_1',
    ]);

    // When scoping to Tenant A
    app()->instance('tenant_id', $this->tenantA->id);
    expect(Table::count())->toBe(1)
        ->and(Table::first()->qr_code_token)->toBe('qr_token_a_1');

    // When scoping to Tenant B
    app()->instance('tenant_id', $this->tenantB->id);
    expect(Table::count())->toBe(1)
        ->and(Table::first()->qr_code_token)->toBe('qr_token_b_1');
});

test('creating model automatically sets tenant_id from application context', function () {
    app()->instance('tenant_id', $this->tenantA->id);

    $category = MenuCategory::create([
        'outlet_id' => $this->outletA->id,
        'name' => 'Makanan Utama',
        'sort_order' => 1,
    ]);

    expect($category->tenant_id)->toBe($this->tenantA->id);
});

test('unscoped query can view all data', function () {
    Table::create([
        'tenant_id' => $this->tenantA->id,
        'outlet_id' => $this->outletA->id,
        'table_number' => 'T1',
        'qr_code_token' => 'qr_token_a_1',
    ]);

    Table::create([
        'tenant_id' => $this->tenantB->id,
        'outlet_id' => $this->outletB->id,
        'table_number' => 'T2',
        'qr_code_token' => 'qr_token_b_2',
    ]);

    app()->forgetInstance('tenant_id');
    expect(Table::withoutGlobalScopes()->count())->toBe(2);
});
