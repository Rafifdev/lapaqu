<?php

use App\Models\Tenant;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
});

test('check subdomain is rate limited after 30 requests per minute', function () {
    for ($i = 1; $i <= 30; $i++) {
        $response = $this->postJson('/api/tenant/check-subdomain', ['subdomain' => "test-resto-{$i}"]);
        $response->assertOk();
    }

    // 31st request is throttled
    $throttledResponse = $this->postJson('/api/tenant/check-subdomain', ['subdomain' => 'test-resto-31']);
    $throttledResponse->assertStatus(429);
});

test('demo tenant seeder created functional demo restaurant with active menu and users', function () {
    $tenant = Tenant::where('subdomain', 'kopi-senopati')->first();
    expect($tenant)->not->toBeNull()
        ->and($tenant->outlets()->count())->toBe(1)
        ->and($tenant->tables()->count())->toBe(5)
        ->and($tenant->menuCategories()->count())->toBe(3)
        ->and($tenant->menuItems()->count())->toBe(5);

    // Verify Demo Owner Login
    $loginResponse = $this->postJson('/api/auth/login', [
        'email' => 'owner@kopisenopati.id',
        'password' => 'RahasiaKopi123!',
    ]);
    $loginResponse->assertOk()
        ->assertJsonPath('user.roles.0', 'owner')
        ->assertJsonPath('tenant.subdomain', 'kopi-senopati');
});
