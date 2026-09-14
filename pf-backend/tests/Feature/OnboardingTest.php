<?php

use App\Models\MenuCategory;
use App\Models\Outlet;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Table;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
});

test('tenant can register successfully via self-service onboarding', function () {
    $response = $this->postJson('/api/onboarding/register', [
        'restaurant_name' => 'Kopi Kenangan Sudirman',
        'subdomain' => 'kopi-sudirman',
        'owner_name' => 'Budi Santoso',
        'owner_email' => 'budi@kopisudirman.test',
        'owner_password' => 'RahasiaKopi123',
        'phone' => '081234567890',
        'plan_code' => 'basic',
    ]);

    $response->assertStatus(201)
        ->assertJsonStructure([
            'message',
            'tenant' => ['id', 'name', 'subdomain', 'status', 'trial_ends_at'],
            'user' => ['id', 'name', 'email', 'roles'],
            'outlet' => ['id', 'name'],
            'token',
            'token_type',
        ]);

    $tenant = Tenant::where('subdomain', 'kopi-sudirman')->first();
    expect($tenant)->not->toBeNull()
        ->and($tenant->status)->toBe('trial')
        ->and($tenant->trial_ends_at->isFuture())->toBeTrue();

    $owner = User::where('email', 'budi@kopisudirman.test')->first();
    expect($owner)->not->toBeNull()
        ->and($owner->hasRole('owner'))->toBeTrue();

    // Verify starter entities
    expect(Outlet::where('tenant_id', $tenant->id)->count())->toBe(1)
        ->and(Table::where('tenant_id', $tenant->id)->count())->toBe(5)
        ->and(MenuCategory::where('tenant_id', $tenant->id)->count())->toBe(3)
        ->and(Subscription::where('tenant_id', $tenant->id)->first()->status)->toBe('trial');

    // Verify generated token works on /api/auth/me
    $token = $response->json('token');
    $meResponse = $this->withToken($token)->getJson('/api/auth/me');
    $meResponse->assertOk()
        ->assertJsonPath('tenant.subdomain', 'kopi-sudirman');
});

test('registration rejects reserved subdomains', function () {
    $response = $this->postJson('/api/onboarding/register', [
        'restaurant_name' => 'Admin Restaurant',
        'subdomain' => 'admin',
        'owner_name' => 'Admin User',
        'owner_email' => 'admin@fake.test',
        'owner_password' => 'Rahasia123',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['subdomain']);
});

test('registration rejects duplicate subdomain and email', function () {
    $this->postJson('/api/onboarding/register', [
        'restaurant_name' => 'Resto Pertama',
        'subdomain' => 'resto-pertama',
        'owner_name' => 'Owner 1',
        'owner_email' => 'owner1@resto.test',
        'owner_password' => 'Rahasia123',
    ])->assertStatus(201);

    // Duplicate subdomain
    $this->postJson('/api/onboarding/register', [
        'restaurant_name' => 'Resto Kedua',
        'subdomain' => 'resto-pertama',
        'owner_name' => 'Owner 2',
        'owner_email' => 'owner2@resto.test',
        'owner_password' => 'Rahasia123',
    ])->assertStatus(422)
        ->assertJsonValidationErrors(['subdomain']);

    // Duplicate email
    $this->postJson('/api/onboarding/register', [
        'restaurant_name' => 'Resto Ketiga',
        'subdomain' => 'resto-ketiga',
        'owner_name' => 'Owner 1',
        'owner_email' => 'owner1@resto.test',
        'owner_password' => 'Rahasia123',
    ])->assertStatus(422)
        ->assertJsonValidationErrors(['owner_email']);
});
