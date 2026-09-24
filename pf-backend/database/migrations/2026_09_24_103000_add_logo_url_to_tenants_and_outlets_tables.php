<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->text('logo_url')->nullable();
        });

        Schema::table('outlets', function (Blueprint $table) {
            $table->text('logo_url')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('outlets', function (Blueprint $table) {
            $table->dropColumn('logo_url');
        });

        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn('logo_url');
        });
    }
};
