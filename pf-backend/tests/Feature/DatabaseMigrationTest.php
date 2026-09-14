<?php

use App\Models\BillingInvoice;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\MenuItemVariantGroup;
use App\Models\MenuItemVariantOption;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderItemOption;
use App\Models\Outlet;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\RefundRequest;
use App\Models\Subscription;
use App\Models\Table;
use App\Models\TableSession;
use App\Models\Tenant;
use App\Models\TenantPaymentAccount;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
});

test('full POS SaaS data hierarchy can be created and queried correctly', function () {
    $plan = Plan::where('code', 'basic')->first();

    $tenant = Tenant::create([
        'name' => 'Kedai Kopi Lapaqu',
        'subdomain' => 'kedai-kopi',
        'status' => 'active',
        'trial_ends_at' => now()->addDays(14),
    ]);

    $subscription = Subscription::create([
        'tenant_id' => $tenant->id,
        'plan_id' => $plan->id,
        'status' => 'active',
        'active_outlets_count' => 1,
        'current_period_start' => now(),
        'current_period_end' => now()->addMonth(),
        'next_billing_date' => now()->addMonth(),
    ]);

    $invoice = BillingInvoice::create([
        'subscription_id' => $subscription->id,
        'invoice_number' => 'INV-20260828-001',
        'amount' => 99000,
        'status' => 'paid',
        'due_date' => now()->addDays(3),
        'paid_at' => now(),
    ]);

    $paymentAccount = TenantPaymentAccount::create([
        'tenant_id' => $tenant->id,
        'bank_code' => 'BCA',
        'bank_account_number' => '1234567890',
        'bank_account_holder_name' => 'Kedai Kopi PT',
        'is_active' => true,
    ]);

    $outlet = Outlet::create([
        'tenant_id' => $tenant->id,
        'name' => 'Cabang Sudirman',
        'address' => 'Jl. Sudirman No. 1',
        'timezone' => 'Asia/Jakarta',
    ]);

    $table = Table::create([
        'tenant_id' => $tenant->id,
        'outlet_id' => $outlet->id,
        'table_number' => '01',
        'capacity' => 4,
        'qr_code_token' => 'qr_secret_token_123',
    ]);

    $session = TableSession::create([
        'table_id' => $table->id,
        'status' => 'active',
        'customer_identifier' => 'cust_session_1',
    ]);

    $category = MenuCategory::create([
        'tenant_id' => $tenant->id,
        'outlet_id' => $outlet->id,
        'name' => 'Coffee',
        'sort_order' => 1,
    ]);

    $item = MenuItem::create([
        'tenant_id' => $tenant->id,
        'outlet_id' => $outlet->id,
        'category_id' => $category->id,
        'name' => 'Espresso Single',
        'base_price' => 20000,
        'is_available' => true,
    ]);

    $variantGroup = MenuItemVariantGroup::create([
        'menu_item_id' => $item->id,
        'name' => 'Sugar Level',
        'is_required' => true,
    ]);

    $variantOption = MenuItemVariantOption::create([
        'variant_group_id' => $variantGroup->id,
        'name' => 'Less Sugar',
        'price_modifier' => 0,
        'is_available' => true,
    ]);

    $user = User::create([
        'tenant_id' => $tenant->id,
        'outlet_id' => $outlet->id,
        'name' => 'Kasir 1',
        'email' => 'kasir1@kedai.test',
        'password' => 'secret123',
    ]);
    $user->assignRole('kasir');

    $order = Order::create([
        'tenant_id' => $tenant->id,
        'outlet_id' => $outlet->id,
        'table_id' => $table->id,
        'table_session_id' => $session->id,
        'order_number' => 'ORD-20260828-001',
        'customer_name' => 'Budi',
        'status' => 'completed',
        'payment_status' => 'paid',
        'order_type' => 'dine_in',
        'total_amount' => 20000,
        'final_amount' => 20000,
    ]);

    $orderItem = OrderItem::create([
        'order_id' => $order->id,
        'menu_item_id' => $item->id,
        'item_name_snapshot' => 'Espresso Single',
        'base_price_snapshot' => 20000,
        'quantity' => 1,
        'subtotal' => 20000,
        'status' => 'served',
    ]);

    $orderItemOption = OrderItemOption::create([
        'order_item_id' => $orderItem->id,
        'variant_option_id' => $variantOption->id,
        'option_name_snapshot' => 'Less Sugar',
        'price_modifier_snapshot' => 0,
    ]);

    $payment = Payment::create([
        'order_id' => $order->id,
        'payment_method' => 'qris',
        'amount' => 20000,
        'status' => 'paid',
        'paid_at' => now(),
    ]);

    expect($order->items)->toHaveCount(1)
        ->and($order->latestPayment->amount)->toBe(20000)
        ->and($paymentAccount->bank_account_number)->toBe('1234567890');
});
