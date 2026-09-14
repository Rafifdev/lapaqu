<?php

use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use PragmaRX\Google2FA\Google2FA;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);

    $this->tenant = Tenant::create([
        'name' => 'Lapaqu Kitchen',
        'subdomain' => 'lapaqu-kitchen',
        'status' => 'active',
        'trial_ends_at' => now()->addDays(14),
    ]);

    $this->user = User::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'John Owner',
        'email' => 'owner@lapaqu.test',
        'password' => Hash::make('Secret123'),
        'is_active' => true,
    ]);
    $this->user->assignRole('owner');
});

test('user can login successfully with valid credentials', function () {
    $response = $this->postJson('/api/auth/login', [
        'email' => 'owner@lapaqu.test',
        'password' => 'Secret123',
    ]);

    $response->assertOk()
        ->assertJsonStructure([
            'user' => ['id', 'name', 'email', 'roles'],
            'token',
            'token_type',
        ]);
});

test('login fails with invalid credentials', function () {
    $response = $this->postJson('/api/auth/login', [
        'email' => 'owner@lapaqu.test',
        'password' => 'WrongPassword',
    ]);

    $response->assertStatus(401)
        ->assertJson(['message' => 'Kombinasi email dan password tidak valid.']);
});

test('login is rate limited after 5 failed attempts', function () {
    for ($i = 0; $i < 5; $i++) {
        $this->postJson('/api/auth/login', [
            'email' => 'owner@lapaqu.test',
            'password' => 'WrongPassword',
        ]);
    }

    $response = $this->postJson('/api/auth/login', [
        'email' => 'owner@lapaqu.test',
        'password' => 'WrongPassword',
    ]);

    $response->assertStatus(429);
});

test('user can setup and verify 2FA TOTP', function () {
    $token = $this->user->createToken('test')->plainTextToken;

    // 1. Setup 2FA
    $setupResponse = $this->withToken($token)->postJson('/api/auth/2fa/setup');
    $setupResponse->assertOk()
        ->assertJsonStructure(['secret', 'qr_code_url']);

    $secret = $setupResponse->json('secret');

    // 2. Generate valid TOTP code
    $google2fa = new Google2FA();
    $validCode = $google2fa->getCurrentOtp($secret);

    // 3. Verify 2FA
    $verifyResponse = $this->withToken($token)->postJson('/api/auth/2fa/verify', [
        'code' => $validCode,
    ]);

    $verifyResponse->assertOk()
        ->assertJson(['two_factor_enabled' => true]);

    $this->user->refresh();
    expect($this->user->two_factor_confirmed_at)->not->toBeNull();
});

test('login requires 2FA challenge when 2FA is confirmed for owner', function () {
    $google2fa = new Google2FA();
    $secret = $google2fa->generateSecretKey();

    $this->user->update([
        'two_factor_secret' => $secret,
        'two_factor_confirmed_at' => now(),
    ]);

    $response = $this->postJson('/api/auth/login', [
        'email' => 'owner@lapaqu.test',
        'password' => 'Secret123',
    ]);

    $response->assertOk()
        ->assertJson([
            'requires_2fa' => true,
        ])
        ->assertJsonStructure(['temp_token']);

    $tempToken = $response->json('temp_token');
    $code = $google2fa->getCurrentOtp($secret);

    $challengeResponse = $this->postJson('/api/auth/2fa/challenge', [
        'temp_token' => $tempToken,
        'code' => $code,
    ]);

    $challengeResponse->assertOk()
        ->assertJsonStructure(['token', 'user']);
});

test('forgot password generates reset token and is rate limited', function () {
    for ($i = 0; $i < 3; $i++) {
        $this->postJson('/api/auth/forgot-password', [
            'email' => 'owner@lapaqu.test',
        ])->assertOk();
    }

    // 4th attempt gets rate limited (3 attempts/hour)
    $this->postJson('/api/auth/forgot-password', [
        'email' => 'owner@lapaqu.test',
    ])->assertStatus(429);
});

test('user can reset password with valid token and password policy', function () {
    $rawToken = 'sample_raw_reset_token_123';
    DB::table('password_reset_tokens')->insert([
        'email' => 'owner@lapaqu.test',
        'token' => Hash::make($rawToken),
        'created_at' => now(),
    ]);

    $response = $this->postJson('/api/auth/reset-password', [
        'email' => 'owner@lapaqu.test',
        'token' => $rawToken,
        'password' => 'NewSecret456',
        'password_confirmation' => 'NewSecret456',
    ]);

    $response->assertOk();

    $this->user->refresh();
    expect(Hash::check('NewSecret456', $this->user->password))->toBeTrue();
});

test('check subdomain returns availability status and rejects reserved words', function () {
    $this->postJson('/api/tenant/check-subdomain', ['subdomain' => 'lapaqu-kitchen'])
        ->assertOk()
        ->assertJson(['available' => false]);

    $this->postJson('/api/tenant/check-subdomain', ['subdomain' => 'resto-baru-123'])
        ->assertOk()
        ->assertJson(['available' => true]);

    $this->postJson('/api/tenant/check-subdomain', ['subdomain' => 'admin'])
        ->assertStatus(422);
});
