<?php

namespace Database\Seeders;

use App\Models\Outlet;
use App\Models\Table;
use App\Models\TableSession;
use App\Models\Tenant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class TableSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::first();
        if (!$tenant) return;

        $outlet = Outlet::where('tenant_id', $tenant->id)->first();
        if (!$outlet) return;

        // 10 Tables with capacities
        $tablesData = [
            ['num' => '01', 'capacity' => 2],
            ['num' => '02', 'capacity' => 4],
            ['num' => '03', 'capacity' => 4],
            ['num' => '04', 'capacity' => 6],
            ['num' => '05', 'capacity' => 2],
            ['num' => '06', 'capacity' => 4],
            ['num' => '07', 'capacity' => 6],
            ['num' => '08', 'capacity' => 4],
            ['num' => '09', 'capacity' => 2],
            ['num' => '10', 'capacity' => 8],
        ];

        foreach ($tablesData as $idx => $tData) {
            $num = $tData['num'];
            $table = Table::updateOrCreate(
                ['tenant_id' => $tenant->id, 'outlet_id' => $outlet->id, 'table_number' => "Meja {$num}"],
                [
                    'capacity' => $tData['capacity'],
                    'qr_code_token' => "qr_senopati_{$num}",
                    'is_active' => true,
                ]
            );

            // Seed active session on table 01 and table 03 for realistic live demonstration
            if (in_array($num, ['01', '03'])) {
                TableSession::firstOrCreate(
                    ['table_id' => $table->id, 'status' => 'active'],
                    [
                        'customer_identifier' => "Pelanggan Meja {$num}",
                        'opened_at' => now()->subMinutes(15 * ($idx + 1)),
                    ]
                );
            }
        }
    }
}
