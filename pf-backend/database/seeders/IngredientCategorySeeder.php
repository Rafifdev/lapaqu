<?php

namespace Database\Seeders;

use App\Models\IngredientCategory;
use App\Models\Outlet;
use Illuminate\Database\Seeder;

class IngredientCategorySeeder extends Seeder
{
    public function run(): void
    {
        $outlets = Outlet::all();

        if ($outlets->isEmpty()) {
            $this->command->warn('Tidak ada outlet ditemukan. Buat tenant dan outlet terlebih dahulu.');
            return;
        }

        $categories = [
            [
                'name' => 'Biji Kopi & Bubuk Kopi',
                'description' => 'Biji kopi espresso blend, single origin arabica/robusta, dan bubuk kopi dasar.',
                'sort_order' => 1,
            ],
            [
                'name' => 'Susu & Olahan Dairy',
                'description' => 'Susu fresh milk, susu UHT, oat milk, susu evaporasi, dan krimer kental.',
                'sort_order' => 2,
            ],
            [
                'name' => 'Sirup & Pemanis',
                'description' => 'Sirup gula aren murni, vanilla, caramel, hazelnut, dan simple syrup.',
                'sort_order' => 3,
            ],
            [
                'name' => 'Teh & Bubuk Flavour',
                'description' => 'Matcha powder, dark chocolate powder, earl grey tea, dan red velvet powder.',
                'sort_order' => 4,
            ],
            [
                'name' => 'Topping & Pelengkap',
                'description' => 'Boba tapioka, grass jelly, biskuit remah, keju spread, dan whipped cream.',
                'sort_order' => 5,
            ],
            [
                'name' => 'Bahan Masak & Dapur',
                'description' => 'Mentega, minyak goreng, saus sambal, saus tomat, dan bumbu pelengkap.',
                'sort_order' => 6,
            ],
            [
                'name' => 'Kemasan & Packaging',
                'description' => 'Cup plastik 16oz/22oz, lid cup, sedotan ramah lingkungan, kantong kresek, dan tissue.',
                'sort_order' => 7,
            ],
        ];

        foreach ($outlets as $outlet) {
            foreach ($categories as $cat) {
                IngredientCategory::firstOrCreate(
                    [
                        'outlet_id' => $outlet->id,
                        'name' => $cat['name'],
                    ],
                    [
                        'tenant_id' => $outlet->tenant_id,
                        'description' => $cat['description'],
                        'sort_order' => $cat['sort_order'],
                    ]
                );
            }
        }

        $this->command->info('Seeder Kategori Bahan Baku berhasil dijalankan untuk ' . $outlets->count() . ' outlet.');
    }
}
