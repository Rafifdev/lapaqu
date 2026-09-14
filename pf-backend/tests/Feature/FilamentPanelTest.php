<?php

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
});

test('superadmin can view filament login page and login to dashboard', function () {
    // 1. Visit /pf-admin/login & password reset request
    $loginPage = $this->get('/pf-admin/login');
    $loginPage->assertOk();

    $resetRequest = $this->get('/pf-admin/password-reset/request');
    $resetRequest->assertOk();

    $verifyOtp = $this->get('/pf-admin/password-reset/verify-otp?email=admin@lapaqu.id');
    $verifyOtp->assertOk();

    // 2. Authenticate as superadmin
    $superadmin = User::where('email', 'admin@lapaqu.id')->first();
    $this->actingAs($superadmin, 'web');

    // 3. Visit /pf-admin dashboard
    $dashboard = $this->get('/pf-admin');
    $dashboard->assertOk();

    // 4. Visit resources
    $tenants = $this->get('/pf-admin/tenants');
    $tenants->assertOk();

    $plans = $this->get('/pf-admin/plans');
    $plans->assertOk();

    $createPlan = $this->get('/pf-admin/plans/create');
    $createPlan->assertOk();

    $invoices = $this->get('/pf-admin/billing-invoices');
    $invoices->assertOk();

    $subscriptions = $this->get('/pf-admin/subscriptions');
    $subscriptions->assertOk();

    $refundRequests = $this->get('/pf-admin/refund-requests');
    $refundRequests->assertOk();

    $auditLogs = $this->get('/pf-admin/audit-logs');
    $auditLogs->assertOk();

    $systemHealth = $this->get('/pf-admin/system-health');
    $systemHealth->assertOk();
});

test('superadmin can authenticate through livewire login form', function () {
    \Livewire\Livewire::test(\App\Filament\Pages\Auth\Login::class)
        ->set('data.email', 'admin@lapaqu.id')
        ->set('data.password', 'password')
        ->call('authenticate')
        ->assertHasNoFormErrors()
        ->assertRedirect();
});
