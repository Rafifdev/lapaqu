<?php

use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Outlet;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);

    $this->tenant = Tenant::create([
        'name' => 'Resto Menu Test',
        'subdomain' => 'resto-menu',
        'status' => 'active',
        'trial_ends_at' => now()->addDays(14),
    ]);

    $this->outlet = Outlet::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Cabang Utama',
    ]);

    $this->owner = User::create([
        'tenant_id' => $this->tenant->id,
        'outlet_id' => $this->outlet->id,
        'name' => 'Owner Menu',
        'email' => 'owner@restomenu.test',
        'password' => 'secret123',
    ]);
    $this->owner->assignRole('owner');

    $this->token = $this->owner->createToken('test')->plainTextToken;
});

test('owner can manage menu categories', function () {
    // 1. Create category
    $createResponse = $this->withToken($this->token)->postJson('/api/menu-categories', [
        'outlet_id' => $this->outlet->id,
        'name' => 'Makanan Utama',
        'sort_order' => 1,
    ]);

    $createResponse->assertStatus(201)
        ->assertJsonPath('category.name', 'Makanan Utama');

    $catId = $createResponse->json('category.id');

    // 2. List categories
    $listResponse = $this->withToken($this->token)->getJson('/api/menu-categories');
    $listResponse->assertOk()
        ->assertJsonCount(1, 'categories');

    // 3. Update category
    $updateResponse = $this->withToken($this->token)->putJson("/api/menu-categories/{$catId}", [
        'name' => 'Main Course (Updated)',
    ]);
    $updateResponse->assertOk()
        ->assertJsonPath('category.name', 'Main Course (Updated)');

    // 4. Delete category
    $deleteResponse = $this->withToken($this->token)->deleteJson("/api/menu-categories/{$catId}");
    $deleteResponse->assertOk();

    expect(MenuCategory::count())->toBe(0)
        ->and(MenuCategory::withTrashed()->count())->toBe(1);
});

test('owner can create menu item with variants and toggle availability', function () {
    $category = MenuCategory::create([
        'tenant_id' => $this->tenant->id,
        'outlet_id' => $this->outlet->id,
        'name' => 'Minuman',
    ]);

    // 1. Create Menu Item with Variant Group & Options
    $createResponse = $this->withToken($this->token)->postJson('/api/menu-items', [
        'outlet_id' => $this->outlet->id,
        'category_id' => $category->id,
        'name' => 'Kopi Susu Gula Aren',
        'description' => 'Espresso dengan susu segar dan gula aren asli',
        'base_price' => 18000,
        'is_available' => true,
        'variant_groups' => [
            [
                'name' => 'Sugar Level',
                'is_required' => true,
                'min_selection' => 1,
                'max_selection' => 1,
                'options' => [
                    ['name' => 'Normal Sugar (100%)', 'price_modifier' => 0],
                    ['name' => 'Less Sugar (50%)', 'price_modifier' => 0],
                ],
            ],
            [
                'name' => 'Extra Topping',
                'is_required' => false,
                'min_selection' => 0,
                'max_selection' => 2,
                'options' => [
                    ['name' => 'Cincau', 'price_modifier' => 3000],
                    ['name' => 'Boba', 'price_modifier' => 4000],
                ],
            ],
        ],
    ]);

    $createResponse->assertStatus(201)
        ->assertJsonPath('item.name', 'Kopi Susu Gula Aren')
        ->assertJsonCount(2, 'item.variant_groups');

    $itemId = $createResponse->json('item.id');

    // 2. Toggle availability
    $toggleResponse = $this->withToken($this->token)->patchJson("/api/menu-items/{$itemId}/toggle-availability");
    $toggleResponse->assertOk()
        ->assertJsonPath('is_available', false);

    $toggleAgain = $this->withToken($this->token)->patchJson("/api/menu-items/{$itemId}/toggle-availability");
    $toggleAgain->assertOk()
        ->assertJsonPath('is_available', true);

    // 3. List menu items
    $listResponse = $this->withToken($this->token)->getJson('/api/menu-items?search=Kopi');
    $listResponse->assertOk()
        ->assertJsonCount(1, 'items');
});
