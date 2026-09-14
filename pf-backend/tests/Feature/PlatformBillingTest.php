<?php

use App\Models\BillingInvoice;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Billing\XenditPlatformBillingService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);

    $this->plan = Plan::where('code', 'basic')->first();

    $this->tenant = Tenant::create([
        'name' => 'Resto Billing Test',
        'subdomain' => 'resto-billing',
        'status' => 'trial',
        'trial_ends_at' => now()->addDays(14),
    ]);

    $this->owner = User::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Owner Billing',
        'email' => 'billing@resto.test',
        'password' => 'secret123',
    ]);
    $this->owner->assignRole('owner');

    $this->subscription = Subscription::create([
        'tenant_id' => $this->tenant->id,
        'plan_id' => $this->plan->id,
        'status' => 'trial',
        'active_outlets_count' => 2,
        'current_period_start' => now(),
        'current_period_end' => now()->addDays(14),
        'next_billing_date' => now()->addDays(14),
    ]);
});

test('xendit billing service generates correct subscription invoice amount', function () {
    $service = new XenditPlatformBillingService();
    $invoice = $service->createSubscriptionInvoice($this->subscription);

    // 2 outlets * 99,000 = 198,000
    expect($invoice->amount)->toBe(198000)
        ->and($invoice->status)->toBe('pending')
        ->and($invoice->invoice_number)->toStartWith('INV-');
});

test('calculate outlet upgrade calculates prorated amount correctly', function () {
    $service = new XenditPlatformBillingService();
    $upgrade = $service->calculateOutletUpgrade($this->subscription, 1);

    expect($upgrade)->toHaveKeys(['additional_outlets', 'cost_per_outlet_monthly', 'days_remaining', 'prorated_amount', 'new_monthly_total'])
        ->and($upgrade['additional_outlets'])->toBe(1)
        ->and($upgrade['new_monthly_total'])->toBe(3 * 99000);
});

test('platform billing webhook activates subscription and tenant upon payment', function () {
    config(['services.xendit.callback_token' => 'test_callback_secret_123']);

    $invoice = BillingInvoice::create([
        'subscription_id' => $this->subscription->id,
        'xendit_invoice_id' => 'xnd_inv_paid_test_1',
        'invoice_number' => 'INV-TEST-001',
        'amount' => 198000,
        'status' => 'pending',
        'due_date' => now()->addDays(3),
    ]);

    $response = $this->withHeader('x-callback-token', 'test_callback_secret_123')
        ->postJson('/api/webhooks/xendit/platform-billing', [
            'id' => 'xnd_inv_paid_test_1',
            'external_id' => 'INV-TEST-001',
            'status' => 'PAID',
            'paid_amount' => 198000,
        ]);

    $response->assertOk()
        ->assertJson(['status' => 'paid']);

    $this->subscription->refresh();
    $this->tenant->refresh();
    $invoice->refresh();

    expect($invoice->status)->toBe('paid')
        ->and($this->subscription->status)->toBe('active')
        ->and($this->tenant->status)->toBe('active');
});

test('platform billing webhook rejects invalid callback token', function () {
    config(['services.xendit.callback_token' => 'valid_secret_token']);

    $response = $this->withHeader('x-callback-token', 'invalid_token')
        ->postJson('/api/webhooks/xendit/platform-billing', [
            'id' => 'xnd_inv_test',
            'status' => 'PAID',
        ]);

    $response->assertStatus(403);
});

test('authenticated owner can view current subscription and create invoice', function () {
    $token = $this->owner->createToken('test')->plainTextToken;

    // 1. Get current subscription
    $subResponse = $this->withToken($token)->getJson('/api/billing/subscription');
    $subResponse->assertOk()
        ->assertJsonPath('tenant.subdomain', 'resto-billing')
        ->assertJsonPath('subscription.status', 'trial');

    // 2. Create invoice
    $createInvResponse = $this->withToken($token)->postJson('/api/billing/invoices/create');
    $createInvResponse->assertStatus(201)
        ->assertJsonStructure(['message', 'invoice' => ['id', 'invoice_number', 'amount']]);

    // 3. List invoices
    $listResponse = $this->withToken($token)->getJson('/api/billing/invoices');
    $listResponse->assertOk()
        ->assertJsonStructure(['invoices']);
});
