<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('menu_categories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('outlet_id')->constrained('outlets')->cascadeOnDelete();
            $table->string('name', 100);
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->softDeletesTz();
            $table->timestamps();

            $table->index('tenant_id');
            $table->index('outlet_id');
        });

        Schema::create('menu_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('outlet_id')->constrained('outlets')->cascadeOnDelete();
            $table->foreignUuid('category_id')->constrained('menu_categories')->restrictOnDelete();
            $table->string('name', 150);
            $table->text('description')->nullable();
            $table->integer('base_price');
            $table->string('image_url', 500)->nullable();
            $table->boolean('is_available')->default(true);
            $table->softDeletesTz();
            $table->timestamps();

            $table->index('tenant_id');
            $table->index('outlet_id');
            $table->index('category_id');
        });

        Schema::create('menu_item_variant_groups', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('menu_item_id')->constrained('menu_items')->cascadeOnDelete();
            $table->string('name', 100);
            $table->boolean('is_required')->default(false);
            $table->integer('min_selection')->default(0);
            $table->integer('max_selection')->default(1);
            $table->softDeletesTz();
            $table->timestamps();

            $table->index('menu_item_id');
        });

        Schema::create('menu_item_variant_options', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('variant_group_id')->constrained('menu_item_variant_groups')->cascadeOnDelete();
            $table->string('name', 100);
            $table->integer('price_modifier')->default(0);
            $table->boolean('is_available')->default(true);
            $table->softDeletesTz();
            $table->timestamps();

            $table->index('variant_group_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('menu_item_variant_options');
        Schema::dropIfExists('menu_item_variant_groups');
        Schema::dropIfExists('menu_items');
        Schema::dropIfExists('menu_categories');
    }
};
