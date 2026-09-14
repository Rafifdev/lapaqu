<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ingredients', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('outlet_id')->constrained('outlets')->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('unit', 20);
            $table->string('base_unit', 20);
            $table->decimal('current_stock', 12, 2)->default(0);
            $table->decimal('low_stock_threshold', 12, 2)->nullable();
            $table->integer('cost_per_unit')->nullable();
            $table->boolean('is_active')->default(true);
            $table->softDeletesTz();
            $table->timestamps();

            $table->index('tenant_id');
            $table->index('outlet_id');
            $table->index('is_active');
        });

        Schema::create('menu_item_recipes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('menu_item_id')->constrained('menu_items')->cascadeOnDelete();
            $table->foreignUuid('ingredient_id')->constrained('ingredients')->cascadeOnDelete();
            $table->decimal('quantity_needed', 10, 2);
            $table->timestamps();

            $table->unique(['menu_item_id', 'ingredient_id']);
            $table->index('menu_item_id');
            $table->index('ingredient_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('menu_item_recipes');
        Schema::dropIfExists('ingredients');
    }
};
