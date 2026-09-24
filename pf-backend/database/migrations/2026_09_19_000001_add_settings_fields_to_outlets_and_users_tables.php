<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('outlets', function (Blueprint $table) {
            $table->string('slogan', 255)->nullable();
            $table->boolean('enable_tax')->default(false);
            $table->integer('tax_percentage')->default(10);
            $table->boolean('enable_service_charge')->default(false);
            $table->integer('service_charge_percentage')->default(5);
            $table->integer('table_timeout')->default(90);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->jsonb('notification_preferences')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('notification_preferences');
        });

        Schema::table('outlets', function (Blueprint $table) {
            $table->dropColumn([
                'slogan',
                'enable_tax',
                'tax_percentage',
                'enable_service_charge',
                'service_charge_percentage',
                'table_timeout',
            ]);
        });
    }
};