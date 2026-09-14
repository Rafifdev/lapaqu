<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_payment_accounts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->unique()->constrained('tenants')->cascadeOnDelete();
            $table->string('xendit_sub_account_id', 100)->nullable()->unique();
            $table->string('bank_code', 20);
            $table->text('bank_account_number');
            $table->string('bank_account_holder_name', 150);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('settlement_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_payment_account_id')->constrained('tenant_payment_accounts')->cascadeOnDelete();
            $table->uuid('order_id')->nullable();
            $table->integer('gross_amount');
            $table->integer('platform_fee')->default(0);
            $table->integer('net_amount');
            $table->string('status', 30)->default('pending');
            $table->string('type', 30)->default('order_settlement');
            $table->timestampTz('settled_at')->nullable();
            $table->timestampTz('created_at')->useCurrent();

            $table->index('tenant_payment_account_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settlement_logs');
        Schema::dropIfExists('tenant_payment_accounts');
    }
};
