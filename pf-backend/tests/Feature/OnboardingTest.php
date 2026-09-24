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
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use App\Notifications\SendRegistrationOtpNotification;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
});

test('tenant can send otp and register successfully via self-service onboarding', function () {
    Notification::fake();

    // 1. Send OTP
    $otpRes = $this->postJson('/api/onboarding/send-otp', [
        'name' => 'Budi Santoso',
        'email' => 'budi@kopisudirman.test',
    ]);
    $otpRes->assertOk();

    Notification::assertSentTo(
        new \Illuminate\Notifications\AnonymousNotifiable,
        SendRegistrationOtpNotification::class
    );

    // Mock fixed OTP for register
    Cache::put('reg_otp_data_budi@kopisudirman.test', [
        'otp_hash' => Hash::make('123456'),
        'attempts' => 0,
    ], now()->addMinutes(10));

    // 2. Register with OTP
    $response = $this->postJson('/api/onboarding/register', [
        'restaurant_name' => 'Kopi Kenangan Sudirman',
        'subdomain' => 'kopi-sudirman',
        'owner_name' => 'Budi Santoso',
        'owner_email' => 'budi@kopisudirman.test',
        'owner_password' => 'RahasiaKopi123',
        'otp' => '123456',
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
        ->and($owner->hasRole('owner'))->toBeTrue()
        ->and($owner->email_verified_at)->not->toBeNull();

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

test('registration rejects invalid or missing otp', function () {
    $response = $this->postJson('/api/onboarding/register', [
        'restaurant_name' => 'Resto Baru',
        'subdomain' => 'resto-baru',
        'owner_name' => 'Owner Baru',
        'owner_email' => 'ownerbaru@resto.test',
        'owner_password' => 'Rahasia123',
        'otp' => '999999',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['otp']);
});

test('registration rejects reserved subdomains', function () {
    $response = $this->postJson('/api/onboarding/register', [
        'restaurant_name' => 'Admin Restaurant',
        'subdomain' => 'admin',
        'owner_name' => 'Admin User',
        'owner_email' => 'admin@fake.test',
        'owner_password' => 'Rahasia123',
        'otp' => '123456',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['subdomain']);
});

test('registration rejects duplicate subdomain and email', function () {
    Cache::put('reg_otp_data_owner1@resto.test', [
        'otp_hash' => Hash::make('123456'),
        'attempts' => 0,
    ], now()->addMinutes(10));

    $this->postJson('/api/onboarding/register', [
        'restaurant_name' => 'Resto Pertama',
        'subdomain' => 'resto-pertama',
        'owner_name' => 'Owner 1',
        'owner_email' => 'owner1@resto.test',
        'owner_password' => 'Rahasia123',
        'otp' => '123456',
    ])->assertStatus(201);

    // Duplicate subdomain
    Cache::put('reg_otp_data_owner2@resto.test', [
        'otp_hash' => Hash::make('123456'),
        'attempts' => 0,
    ], now()->addMinutes(10));

    $this->postJson('/api/onboarding/register', [
        'restaurant_name' => 'Resto Kedua',
        'subdomain' => 'resto-pertama',
        'owner_name' => 'Owner 2',
        'owner_email' => 'owner2@resto.test',
        'owner_password' => 'Rahasia123',
        'otp' => '123456',
    ])->assertStatus(422)
        ->assertJsonValidationErrors(['subdomain']);

    // Duplicate email
    $this->postJson('/api/onboarding/register', [
        'restaurant_name' => 'Resto Ketiga',
        'subdomain' => 'resto-ketiga',
        'owner_name' => 'Owner 1',
        'owner_email' => 'owner1@resto.test',
        'owner_password' => 'Rahasia123',
        'otp' => '123456',
    ])->assertStatus(422)
        ->assertJsonValidationErrors(['owner_email']);
});
