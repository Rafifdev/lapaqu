<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code', 50)->unique();
            $table->string('name', 100);
            $table->integer('price_per_outlet_monthly');
            $table->integer('max_tables_per_outlet')->nullable();
            $table->integer('max_users_per_outlet')->nullable();
            $table->integer('max_outlets')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('tenants', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name', 150);
            $table->string('subdomain', 50)->unique();
            $table->string('status', 30)->default('trial');
            $table->timestampTz('trial_ends_at');
            $table->timestampTz('churned_at')->nullable();
            $table->softDeletesTz();
            $table->timestamps();
        });

        Schema::create('subscriptions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('plan_id')->constrained('plans')->restrictOnDelete();
            $table->string('status', 30)->default('trial');
            $table->integer('active_outlets_count')->default(1);
            $table->timestampTz('current_period_start');
            $table->timestampTz('current_period_end');
            $table->timestampTz('next_billing_date');
            $table->timestamps();

            $table->index('tenant_id');
            $table->index('status');
        });

        Schema::create('billing_invoices', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('subscription_id')->constrained('subscriptions')->cascadeOnDelete();
            $table->string('xendit_invoice_id', 100)->nullable()->unique();
            $table->string('invoice_number', 50)->unique();
            $table->integer('amount');
            $table->string('status', 30)->default('pending');
            $table->timestampTz('due_date');
            $table->timestampTz('paid_at')->nullable();
            $table->timestamps();

            $table->index('subscription_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_invoices');
        Schema::dropIfExists('subscriptions');
        Schema::dropIfExists('tenants');
        Schema::dropIfExists('plans');
    }
};
