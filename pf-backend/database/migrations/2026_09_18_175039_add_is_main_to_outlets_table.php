<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('outlets', function (Blueprint $table) {
            $table->boolean('is_main')->default(false);
        });

        $tenants = DB::table('outlets')->select('tenant_id')->distinct()->get();
        foreach ($tenants as $t) {
            $mainOutlet = DB::table('outlets')
                ->where('tenant_id', $t->tenant_id)
                ->whereRaw("LOWER(name) LIKE '%utama%'")
                ->first();

            if (!$mainOutlet) {
                $mainOutlet = DB::table('outlets')
                    ->where('tenant_id', $t->tenant_id)
                    ->orderBy('created_at', 'asc')
                    ->first();
            }

            if ($mainOutlet) {
                DB::table('outlets')->where('id', $mainOutlet->id)->update(['is_main' => true]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('outlets', function (Blueprint $table) {
            $table->dropColumn('is_main');
        });
    }
};
