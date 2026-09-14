<?php

use App\Models\Outlet;
use App\Models\Tenant;
use App\Models\TenantPaymentAccount;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);

    $this->tenant = Tenant::create([
        'name' => 'Resto Bank Test',
        'subdomain' => 'resto-bank',
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
        'name' => 'Owner Bank',
        'email' => 'owner@restobank.test',
        'password' => 'secret123',
    ]);
    $this->owner->assignRole('owner');

    $this->token = $this->owner->createToken('test')->plainTextToken;
});

test('owner can configure bank account with encryption and view masked account number', function () {
    // 1. Initial state is not configured
    $getInitial = $this->withToken($this->token)->getJson('/api/payment-account');
    $getInitial->assertOk()
        ->assertJsonPath('is_configured', false);

    // 2. Setup Bank Account
    $setupResponse = $this->withToken($this->token)->postJson('/api/payment-account', [
        'bank_code' => 'BCA',
        'bank_account_number' => '1234567890',
        'bank_account_holder_name' => 'PT Resto Sejahtera',
    ]);

    $setupResponse->assertStatus(201)
        ->assertJsonPath('payment_account.bank_code', 'BCA');

    // 3. Verify in DB is encrypted
    $rawRecord = DB::table('tenant_payment_accounts')->where('tenant_id', $this->tenant->id)->first();
    expect($rawRecord->bank_account_number)->not->toBe('1234567890'); // Must be encrypted ciphertext

    // 4. View payment account returns masked number
    $getResponse = $this->withToken($this->token)->getJson('/api/payment-account');
    $getResponse->assertOk()
        ->assertJsonPath('is_configured', true)
        ->assertJsonPath('payment_account.bank_account_number', '******7890');
});
