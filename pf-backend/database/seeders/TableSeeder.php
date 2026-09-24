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
        $outlets = Outlet::all();
        if ($outlets->isEmpty()) return;

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

        foreach ($outlets as $outlet) {
            $tenant = $outlet->tenant;
            if (!$tenant) continue;

            foreach ($tablesData as $idx => $tData) {
                $num = $tData['num'];
                $table = Table::updateOrCreate(
                    ['tenant_id' => $tenant->id, 'outlet_id' => $outlet->id, 'table_number' => "Meja {$num}"],
                    [
                        'capacity' => $tData['capacity'],
                        'qr_code_token' => "qr_{$outlet->id}_{$num}",
                        'is_active' => true,
                    ]
                );

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
}
