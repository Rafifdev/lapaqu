<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->foreignUuid('outlet_id')->constrained('outlets')->restrictOnDelete();
            $table->foreignUuid('table_id')->nullable()->constrained('tables')->nullOnDelete();
            $table->foreignUuid('table_session_id')->nullable()->constrained('table_sessions')->nullOnDelete();
            $table->string('order_number', 50);
            $table->string('customer_name', 100);
            $table->string('status', 30)->default('pending_payment');
            $table->string('payment_status', 30)->default('unpaid');
            $table->string('order_type', 30)->default('dine_in');
            $table->integer('total_amount');
            $table->integer('discount_amount')->default(0);
            $table->integer('final_amount');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('tenant_id');
            $table->index('outlet_id');
            $table->index('status');
            $table->index('payment_status');
            $table->index('created_at');
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignUuid('menu_item_id')->constrained('menu_items')->restrictOnDelete();
            $table->string('item_name_snapshot', 150);
            $table->integer('base_price_snapshot');
            $table->integer('quantity');
            $table->integer('subtotal');
            $table->string('status', 30)->default('pending');
            $table->text('notes')->nullable();
            $table->boolean('is_voided')->default(false);
            $table->foreignUuid('voided_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('voided_at')->nullable();
            $table->timestamps();

            $table->index('order_id');
            $table->index('menu_item_id');
        });

        Schema::create('order_item_options', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('order_item_id')->constrained('order_items')->cascadeOnDelete();
            $table->foreignUuid('variant_option_id')->nullable()->constrained('menu_item_variant_options')->nullOnDelete();
            $table->string('option_name_snapshot', 100);
            $table->integer('price_modifier_snapshot')->default(0);
            $table->timestampTz('created_at')->useCurrent();

            $table->index('order_item_id');
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('order_id')->constrained('orders')->cascadeOnDelete();
            $table->string('xendit_transaction_id', 100)->nullable()->unique();
            $table->string('payment_method', 30);
            $table->integer('amount');
            $table->string('status', 30)->default('pending');
            $table->jsonb('raw_payload')->nullable();
            $table->timestampTz('paid_at')->nullable();
            $table->timestamps();

            $table->index('order_id');
            $table->index('status');
        });

        Schema::create('refund_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignUuid('requested_by_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignUuid('reviewed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->integer('amount');
            $table->text('reason');
            $table->string('status', 30)->default('pending');
            $table->string('xendit_refund_id', 100)->nullable();
            $table->timestampTz('reviewed_at')->nullable();
            $table->timestampTz('processed_at')->nullable();
            $table->timestamps();

            $table->index('order_id');
            $table->index('status');
        });

        Schema::table('settlement_logs', function (Blueprint $table) {
            $table->foreign('order_id')->references('id')->on('orders')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('settlement_logs', function (Blueprint $table) {
            $table->dropForeign(['order_id']);
        });

        Schema::dropIfExists('refund_requests');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('order_item_options');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
    }
};
