<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Kategori Bahan Baku
        Schema::create('ingredient_categories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('outlet_id')->constrained('outlets')->cascadeOnDelete();
            $table->string('name', 100);
            $table->text('description')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'outlet_id']);
        });

        // 2. Tambah category_id ke tabel ingredients
        Schema::table('ingredients', function (Blueprint $table) {
            $table->foreignUuid('category_id')->nullable()->after('outlet_id')->constrained('ingredient_categories')->nullOnDelete();
        });

        // 3. Riwayat / Buku Besar Mutasi Stok
        Schema::create('ingredient_stock_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('outlet_id')->constrained('outlets')->cascadeOnDelete();
            $table->foreignUuid('ingredient_id')->constrained('ingredients')->cascadeOnDelete();
            $table->string('type', 30); // order_deduction, restock, manual_set, opname_adjustment
            $table->decimal('quantity', 12, 2); // Kuantitas mutasi (dalam display unit atau base unit)
            $table->string('unit', 20);
            $table->decimal('balance_before', 12, 2);
            $table->decimal('balance_after', 12, 2);
            $table->string('reference_id', 100)->nullable(); // e.g. order_number atau opname_id
            $table->text('notes')->nullable();
            $table->string('created_by', 100)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['tenant_id', 'outlet_id', 'ingredient_id']);
            $table->index('type');
            $table->index('created_at');
        });

        // 4. Sesi Stok Opname
        Schema::create('stock_opnames', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('outlet_id')->constrained('outlets')->cascadeOnDelete();
            $table->string('opname_number', 50)->unique();
            $table->date('date');
            $table->string('status', 20)->default('completed'); // completed
            $table->text('notes')->nullable();
            $table->integer('total_items')->default(0);
            $table->integer('total_variance_items')->default(0);
            $table->string('created_by', 100)->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'outlet_id', 'date']);
        });

        // 5. Item Detail Stok Opname
        Schema::create('stock_opname_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('stock_opname_id')->constrained('stock_opnames')->cascadeOnDelete();
            $table->foreignUuid('ingredient_id')->constrained('ingredients')->cascadeOnDelete();
            $table->decimal('system_stock', 12, 2);
            $table->decimal('physical_stock', 12, 2);
            $table->decimal('difference', 12, 2); // physical_stock - system_stock
            $table->string('unit', 20);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('stock_opname_id');
            $table->index('ingredient_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_opname_items');
        Schema::dropIfExists('stock_opnames');
        Schema::dropIfExists('ingredient_stock_logs');

        Schema::table('ingredients', function (Blueprint $table) {
            $table->dropForeign(['category_id']);
            $table->dropColumn('category_id');
        });

        Schema::dropIfExists('ingredient_categories');
    }
};
