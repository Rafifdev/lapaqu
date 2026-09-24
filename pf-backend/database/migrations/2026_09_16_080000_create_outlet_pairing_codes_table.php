<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('outlet_pairing_codes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('outlet_id')->constrained('outlets')->cascadeOnDelete();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('code', 10)->index();
            $table->string('device_name', 100)->nullable();
            $table->string('status', 20)->default('active'); // active, used, expired
            $table->timestampTz('expires_at');
            $table->timestampTz('used_at')->nullable();
            $table->timestamps();

            $table->index(['code', 'status']);
            $table->index('outlet_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('outlet_pairing_codes');
    }
};
