<?php

use App\Models\AuditLog;
use App\Models\Order;
use App\Models\Outlet;
use App\Models\Payment;
use App\Models\RefundRequest;
use App\Models\Tenant;
use App\Models\TenantPaymentAccount;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);

    $this->tenant = Tenant::create([
        'name' => 'Resto Refund Test',
        'subdomain' => 'resto-refund',
        'status' => 'active',
        'trial_ends_at' => now()->addDays(14),
    ]);

    $this->outlet = Outlet::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Cabang Refund',
    ]);

    $this->owner = User::create([
        'tenant_id' => $this->tenant->id,
        'outlet_id' => $this->outlet->id,
        'name' => 'Owner Refund',
        'email' => 'owner@restorefund.test',
        'password' => 'secret123',
    ]);
    $this->owner->assignRole('owner');
    $this->ownerToken = $this->owner->createToken('owner-token')->plainTextToken;

    $this->kasir = User::create([
        'tenant_id' => $this->tenant->id,
        'outlet_id' => $this->outlet->id,
        'name' => 'Kasir Doni',
        'email' => 'kasir@restorefund.test',
        'password' => 'secret123',
    ]);
    $this->kasir->assignRole('kasir');
    $this->kasirToken = $this->kasir->createToken('kasir-token')->plainTextToken;

    $this->paymentAccount = TenantPaymentAccount::create([
        'tenant_id' => $this->tenant->id,
        'xendit_sub_account_id' => 'xnd_sub_refund_test',
        'bank_code' => 'BCA',
        'bank_account_number' => '1234567890',
        'bank_account_holder_name' => 'Resto Refund',
        'is_active' => true,
    ]);

    $this->order = Order::create([
        'tenant_id' => $this->tenant->id,
        'outlet_id' => $this->outlet->id,
        'order_number' => 'REF-ORD-001',
        'customer_name' => 'Pelanggan Komplain',
        'order_type' => 'dine_in',
        'status' => 'processing',
        'payment_status' => 'paid',
        'total_amount' => 50000,
        'discount_amount' => 0,
        'final_amount' => 50000,
    ]);

    $this->payment = Payment::create([
        'order_id' => $this->order->id,
        'payment_method' => 'qris',
        'amount' => 50000,
        'status' => 'paid',
        'paid_at' => now(),
    ]);
});

test('kasir can submit refund request', function () {
    $requestResponse = $this->withToken($this->kasirToken)->postJson("/api/orders/{$this->order->id}/refund", [
        'reason' => 'Makanan tumpah oleh pramusaji',
        'amount' => 50000,
    ]);

    $requestResponse->assertStatus(201)
        ->assertJsonPath('refund_request.status', 'pending');
});

test('owner can approve refund', function () {
    $refund = RefundRequest::create([
        'order_id' => $this->order->id,
        'requested_by_user_id' => $this->kasir->id,
        'amount' => 50000,
        'reason' => 'Makanan tumpah oleh pramusaji',
        'status' => 'pending',
    ]);

    $approveResponse = $this->withToken($this->ownerToken)->postJson("/api/refunds/{$refund->id}/approve");
    $approveResponse->assertOk()
        ->assertJsonPath('refund_request.status', 'approved');

    $this->order->refresh();
    $this->payment->refresh();
    expect($this->order->status)->toBe('refunded')
        ->and($this->payment->status)->toBe('refunded');

    $auditLog = AuditLog::where('action', 'order.refund.approved')->first();
    expect($auditLog)->not->toBeNull()
        ->and($auditLog->target_id)->toBe($this->order->id);
});

test('owner can reject refund with reason', function () {
    $refund = RefundRequest::create([
        'order_id' => $this->order->id,
        'requested_by_user_id' => $this->kasir->id,
        'amount' => 50000,
        'reason' => 'Pelanggan minta diskon terlambat',
        'status' => 'pending',
    ]);

    $rejectResponse = $this->withToken($this->ownerToken)->postJson("/api/refunds/{$refund->id}/reject", [
        'rejection_reason' => 'Permintaan tidak sesuai kebijakan resto',
    ]);

    $rejectResponse->assertOk()
        ->assertJsonPath('refund_request.status', 'rejected');

    $refund->refresh();
    expect($refund->status)->toBe('rejected')
        ->and($this->order->status)->toBe('processing');
});
