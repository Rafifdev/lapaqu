<?php

namespace Database\Seeders;

use App\Models\Ingredient;
use App\Models\IngredientCategory;
use App\Models\IngredientStockLog;
use App\Models\MenuItem;
use App\Models\MenuItemRecipe;
use App\Models\Outlet;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class IngredientSeeder extends Seeder
{
    public function run(): void
    {
        $outlets = Outlet::all();

        if ($outlets->isEmpty()) {
            $this->command->warn('Tidak ada outlet ditemukan.');
            return;
        }

        $ingredientsMaster = [
            // ==========================================
            // 1. BIJI KOPI & BUBUK KOPI
            // ==========================================
            [
                'category' => 'Biji Kopi & Bubuk Kopi',
                'name' => 'Biji Kopi Espresso Blend (70% Arabica + 30% Robusta)',
                'unit' => 'kg',
                'current_stock' => 14.25,
                'low_stock_threshold' => 3.00,
                'cost_per_unit' => 250000, // Rp 250.000 / kg -> Rp 250 / g
                'is_active' => true,
                'supplier' => 'CV Mitra Roastery Nusantara',
            ],
            [
                'category' => 'Biji Kopi & Bubuk Kopi',
                'name' => 'Biji Kopi Single Origin Gayo Wine Process',
                'unit' => 'kg',
                'current_stock' => 1.75, // DI BAWAH BATAS MINIMUM (Menipis!)
                'low_stock_threshold' => 2.00,
                'cost_per_unit' => 280000, // Rp 280.000 / kg -> Rp 280 / g
                'is_active' => true,
                'supplier' => 'Koperasi Kopi Gayo Mandiri',
            ],
            [
                'category' => 'Biji Kopi & Bubuk Kopi',
                'name' => 'Biji Kopi Single Origin Flores Bajawa',
                'unit' => 'kg',
                'current_stock' => 6.50,
                'low_stock_threshold' => 1.50,
                'cost_per_unit' => 230000, // Rp 230.000 / kg -> Rp 230 / g
                'is_active' => true,
                'supplier' => 'CV Mitra Roastery Nusantara',
            ],
            [
                'category' => 'Biji Kopi & Bubuk Kopi',
                'name' => 'Bubuk Kopi Robusta Dampit Fine Grind',
                'unit' => 'kg',
                'current_stock' => 9.20,
                'low_stock_threshold' => 2.50,
                'cost_per_unit' => 110000, // Rp 110.000 / kg -> Rp 110 / g
                'is_active' => true,
                'supplier' => 'Sentra Kopi Dampit Malang',
            ],

            // ==========================================
            // 2. SUSU & OLAHAN DAIRY
            // ==========================================
            [
                'category' => 'Susu & Olahan Dairy',
                'name' => 'Susu Fresh Milk Pasteurisasi Greenfield',
                'unit' => 'l',
                'current_stock' => 28.50,
                'low_stock_threshold' => 6.00,
                'cost_per_unit' => 24000, // Rp 24.000 / l -> Rp 24 / ml
                'is_active' => true,
                'supplier' => 'PT Greenfields Dairy Indonesia',
            ],
            [
                'category' => 'Susu & Olahan Dairy',
                'name' => 'Susu UHT Full Cream Diamond',
                'unit' => 'l',
                'current_stock' => 36.00,
                'low_stock_threshold' => 8.00,
                'cost_per_unit' => 19500, // Rp 19.500 / l -> Rp 19.5 / ml
                'is_active' => true,
                'supplier' => 'PT Diamond Cold Storage',
            ],
            [
                'category' => 'Susu & Olahan Dairy',
                'name' => 'Oat Milk Barista Edition Oatside',
                'unit' => 'l',
                'current_stock' => 2.00, // DI BAWAH BATAS MINIMUM (Menipis!)
                'low_stock_threshold' => 3.00,
                'cost_per_unit' => 42000, // Rp 42.000 / l -> Rp 42 / ml
                'is_active' => true,
                'supplier' => 'PT Toffin Indonesia',
            ],
            [
                'category' => 'Susu & Olahan Dairy',
                'name' => 'Susu Evaporasi Carnation Can 405g',
                'unit' => 'pcs',
                'current_stock' => 18.00,
                'low_stock_threshold' => 5.00,
                'cost_per_unit' => 16500, // Rp 16.500 / pcs
                'is_active' => true,
                'supplier' => 'Distributor Sembako Makmur',
            ],
            [
                'category' => 'Susu & Olahan Dairy',
                'name' => 'Krimer Kental Manis Frisian Flag Pouch 560g',
                'unit' => 'pcs',
                'current_stock' => 24.00,
                'low_stock_threshold' => 5.00,
                'cost_per_unit' => 14000, // Rp 14.000 / pcs
                'is_active' => true,
                'supplier' => 'Distributor Sembako Makmur',
            ],

            // ==========================================
            // 3. SIRUP & PEMANIS
            // ==========================================
            [
                'category' => 'Sirup & Pemanis',
                'name' => 'Sirup Gula Aren Murni Organik',
                'unit' => 'l',
                'current_stock' => 16.40,
                'low_stock_threshold' => 4.00,
                'cost_per_unit' => 45000, // Rp 45.000 / l -> Rp 45 / ml
                'is_active' => true,
                'supplier' => 'Gula Aren Ciamis Berkah',
            ],
            [
                'category' => 'Sirup & Pemanis',
                'name' => 'Sirup Vanilla Monin 700ml',
                'unit' => 'pcs',
                'current_stock' => 6.00,
                'low_stock_threshold' => 2.00,
                'cost_per_unit' => 135000, // Rp 135.000 / pcs
                'is_active' => true,
                'supplier' => 'PT Toffin Indonesia',
            ],
            [
                'category' => 'Sirup & Pemanis',
                'name' => 'Sirup Salted Caramel Toffin 750ml',
                'unit' => 'pcs',
                'current_stock' => 1.00, // DI BAWAH BATAS MINIMUM (Menipis!)
                'low_stock_threshold' => 2.00,
                'cost_per_unit' => 110000, // Rp 110.000 / pcs
                'is_active' => true,
                'supplier' => 'PT Toffin Indonesia',
            ],
            [
                'category' => 'Sirup & Pemanis',
                'name' => 'Sirup Hazelnut Denali 750ml',
                'unit' => 'pcs',
                'current_stock' => 4.00,
                'low_stock_threshold' => 1.00,
                'cost_per_unit' => 98000, // Rp 98.000 / pcs
                'is_active' => true,
                'supplier' => 'PT Toffin Indonesia',
            ],
            [
                'category' => 'Sirup & Pemanis',
                'name' => 'Seasonal Sirup Sakura Blossom (Musiman)',
                'unit' => 'pcs',
                'current_stock' => 0.00,
                'low_stock_threshold' => 2.00,
                'cost_per_unit' => 145000,
                'is_active' => false, // NON-AKTIF untuk showcase status tabel
                'supplier' => 'PT Toffin Indonesia',
            ],

            // ==========================================
            // 4. TEH & BUBUK FLAVOUR
            // ==========================================
            [
                'category' => 'Teh & Bubuk Flavour',
                'name' => 'Matcha Powder Uji Ceremonial Grade',
                'unit' => 'kg',
                'current_stock' => 3.80,
                'low_stock_threshold' => 1.00,
                'cost_per_unit' => 210000, // Rp 210.000 / kg -> Rp 210 / g
                'is_active' => true,
                'supplier' => 'Matcha Import Specialist',
            ],
            [
                'category' => 'Teh & Bubuk Flavour',
                'name' => 'Dark Chocolate Powder Belgia 70%',
                'unit' => 'kg',
                'current_stock' => 7.20,
                'low_stock_threshold' => 2.00,
                'cost_per_unit' => 95000, // Rp 95.000 / kg -> Rp 95 / g
                'is_active' => true,
                'supplier' => 'CV Prima Cokelat',
            ],
            [
                'category' => 'Teh & Bubuk Flavour',
                'name' => 'Daun Teh Hitam Earl Grey Premium',
                'unit' => 'kg',
                'current_stock' => 3.50,
                'low_stock_threshold' => 1.00,
                'cost_per_unit' => 125000, // Rp 125.000 / kg -> Rp 125 / g
                'is_active' => true,
                'supplier' => 'Tea Heritage Indonesia',
            ],
            [
                'category' => 'Teh & Bubuk Flavour',
                'name' => 'Red Velvet Powder Artisan',
                'unit' => 'kg',
                'current_stock' => 0.80, // DI BAWAH BATAS MINIMUM (Menipis!)
                'low_stock_threshold' => 1.00,
                'cost_per_unit' => 82000, // Rp 82.000 / kg -> Rp 82 / g
                'is_active' => true,
                'supplier' => 'CV Prima Flavour',
            ],

            // ==========================================
            // 5. TOPPING & PELENGKAP
            // ==========================================
            [
                'category' => 'Topping & Pelengkap',
                'name' => 'Boba Tapioka Pearl Brown Sugar',
                'unit' => 'kg',
                'current_stock' => 21.50,
                'low_stock_threshold' => 5.00,
                'cost_per_unit' => 28000, // Rp 28.000 / kg -> Rp 28 / g
                'is_active' => true,
                'supplier' => 'Boba Supplier Mandiri',
            ],
            [
                'category' => 'Topping & Pelengkap',
                'name' => 'Grass Jelly Herbal (Cincau Hitam)',
                'unit' => 'kg',
                'current_stock' => 9.00,
                'low_stock_threshold' => 2.50,
                'cost_per_unit' => 19000, // Rp 19.000 / kg -> Rp 19 / g
                'is_active' => true,
                'supplier' => 'Sentra Bahan Segar Tradisional',
            ],
            [
                'category' => 'Topping & Pelengkap',
                'name' => 'Biskuit Lotus Biscoff Crumb Repack',
                'unit' => 'kg',
                'current_stock' => 4.50,
                'low_stock_threshold' => 1.50,
                'cost_per_unit' => 95000, // Rp 95.000 / kg -> Rp 95 / g
                'is_active' => true,
                'supplier' => 'CV Baking Ingredients Hub',
            ],
            [
                'category' => 'Topping & Pelengkap',
                'name' => 'Keju Oles Cream Cheese Anchor',
                'unit' => 'kg',
                'current_stock' => 3.20,
                'low_stock_threshold' => 1.00,
                'cost_per_unit' => 115000, // Rp 115.000 / kg -> Rp 115 / g
                'is_active' => true,
                'supplier' => 'PT Fonterra Brands Indonesia',
            ],

            // ==========================================
            // 6. BAHAN MASAK & DAPUR
            // ==========================================
            [
                'category' => 'Bahan Masak & Dapur',
                'name' => 'Daging Sapi US Shortplate Slice 1.5mm',
                'unit' => 'kg',
                'current_stock' => 12.50,
                'low_stock_threshold' => 3.00,
                'cost_per_unit' => 135000, // Rp 135.000 / kg -> Rp 135 / g
                'is_active' => true,
                'supplier' => 'CV Prima Meat Import',
            ],
            [
                'category' => 'Bahan Masak & Dapur',
                'name' => 'Beras Pandan Wangi Cianjur Super',
                'unit' => 'kg',
                'current_stock' => 42.00,
                'low_stock_threshold' => 10.00,
                'cost_per_unit' => 17000, // Rp 17.000 / kg -> Rp 17 / g
                'is_active' => true,
                'supplier' => 'Gudang Beras Berkah Tani',
            ],
            [
                'category' => 'Bahan Masak & Dapur',
                'name' => 'Minyak Goreng SunCo Pouch 2 Liter',
                'unit' => 'l',
                'current_stock' => 24.00,
                'low_stock_threshold' => 6.00,
                'cost_per_unit' => 18000, // Rp 18.000 / l -> Rp 18 / ml
                'is_active' => true,
                'supplier' => 'Distributor Sembako Makmur',
            ],
            [
                'category' => 'Bahan Masak & Dapur',
                'name' => 'Saus Lada Hitam Black Pepper Saori',
                'unit' => 'l',
                'current_stock' => 6.50,
                'low_stock_threshold' => 2.00,
                'cost_per_unit' => 46000, // Rp 46.000 / l -> Rp 46 / ml
                'is_active' => true,
                'supplier' => 'Distributor Bahan Saus Resto',
            ],
            [
                'category' => 'Bahan Masak & Dapur',
                'name' => 'Telur Ayam Negeri Fresh Grade A',
                'unit' => 'kg',
                'current_stock' => 16.00,
                'low_stock_threshold' => 4.00,
                'cost_per_unit' => 29000, // Rp 29.000 / kg -> Rp 29 / g
                'is_active' => true,
                'supplier' => 'Peternakan Ayam Makmur Jaya',
            ],
            [
                'category' => 'Bahan Masak & Dapur',
                'name' => 'Kentang Beku Shoestring Aviko 2.5kg',
                'unit' => 'kg',
                'current_stock' => 15.00,
                'low_stock_threshold' => 5.00,
                'cost_per_unit' => 38000, // Rp 38.000 / kg -> Rp 38 / g
                'is_active' => true,
                'supplier' => 'CV Frozen Food Nusantara',
            ],
            [
                'category' => 'Bahan Masak & Dapur',
                'name' => 'Minyak Truffle Oil White Alba 250ml',
                'unit' => 'pcs',
                'current_stock' => 2.00,
                'low_stock_threshold' => 1.00,
                'cost_per_unit' => 185000, // Rp 185.000 / pcs
                'is_active' => true,
                'supplier' => 'Gourmet Ingredients Specialist',
            ],

            // ==========================================
            // 7. KEMASAN & PACKAGING
            // ==========================================
            [
                'category' => 'Kemasan & Packaging',
                'name' => 'Cup Plastik PET 16oz Sablon Logo + Lid Datar',
                'unit' => 'pcs',
                'current_stock' => 840.00,
                'low_stock_threshold' => 200.00,
                'cost_per_unit' => 680, // Rp 680 / pcs
                'is_active' => true,
                'supplier' => 'CV Mitra Packaging Cupindo',
            ],
            [
                'category' => 'Kemasan & Packaging',
                'name' => 'Cup Panas Double Wall 8oz Paper Cup + Lid',
                'unit' => 'pcs',
                'current_stock' => 65.00, // DI BAWAH BATAS MINIMUM (Menipis!)
                'low_stock_threshold' => 80.00,
                'cost_per_unit' => 850, // Rp 850 / pcs
                'is_active' => true,
                'supplier' => 'CV Mitra Packaging Cupindo',
            ],
            [
                'category' => 'Kemasan & Packaging',
                'name' => 'Sedotan Runcing Steril Bungkus Kertas',
                'unit' => 'pcs',
                'current_stock' => 1450.00,
                'low_stock_threshold' => 300.00,
                'cost_per_unit' => 120, // Rp 120 / pcs
                'is_active' => true,
                'supplier' => 'CV Eco Straw Nusantara',
            ],
            [
                'category' => 'Kemasan & Packaging',
                'name' => 'Paper Bag Kraft Sablon Handle Tali',
                'unit' => 'pcs',
                'current_stock' => 380.00,
                'low_stock_threshold' => 80.00,
                'cost_per_unit' => 1400, // Rp 1.400 / pcs
                'is_active' => true,
                'supplier' => 'Percetakan Kemasan Kraftindo',
            ],
            [
                'category' => 'Kemasan & Packaging',
                'name' => 'Paper Bowl 650ml Rice Bowl + Lid Transparan',
                'unit' => 'pcs',
                'current_stock' => 240.00,
                'low_stock_threshold' => 50.00,
                'cost_per_unit' => 1250, // Rp 1.250 / pcs
                'is_active' => true,
                'supplier' => 'Percetakan Kemasan Kraftindo',
            ],
        ];

        foreach ($outlets as $outlet) {
            // Bersihkan resep dan data lama untuk reset fresh
            MenuItemRecipe::whereHas('menuItem', function ($q) use ($outlet) {
                $q->where('outlet_id', $outlet->id);
            })->delete();

            IngredientStockLog::where('outlet_id', $outlet->id)->delete();
            Ingredient::where('outlet_id', $outlet->id)->forceDelete();

            $createdIngredients = [];

            foreach ($ingredientsMaster as $item) {
                $category = IngredientCategory::where('outlet_id', $outlet->id)
                    ->where('name', $item['category'])
                    ->first();

                // Simpan dalam base unit sesuai standar sistem inventory
                $stockInBase = Ingredient::toBaseUnit((float) $item['current_stock'], $item['unit']);
                $thresholdInBase = Ingredient::toBaseUnit((float) $item['low_stock_threshold'], $item['unit']);

                $ing = Ingredient::create([
                    'tenant_id' => $outlet->tenant_id,
                    'outlet_id' => $outlet->id,
                    'category_id' => $category?->id,
                    'name' => $item['name'],
                    'unit' => $item['unit'],
                    'base_unit' => Ingredient::resolveBaseUnit($item['unit']),
                    'current_stock' => $stockInBase,
                    'low_stock_threshold' => $thresholdInBase,
                    'cost_per_unit' => $item['cost_per_unit'],
                    'is_active' => $item['is_active'],
                ]);

                $createdIngredients[$item['name']] = $ing;

                // =========================================================================
                // BUKU BESAR MUTASI STOK (LOGS): MENCIPTAKAN DATA YANG HIDUP & BERSEJARAH
                // =========================================================================
                $initialBatch = $item['current_stock'] + 8.00;
                $supplier = $item['supplier'] ?? 'CV Mitra Pangan Utama';

                // Log 1: Penerimaan restock awal 5 hari lalu
                IngredientStockLog::create([
                    'tenant_id' => $outlet->tenant_id,
                    'outlet_id' => $outlet->id,
                    'ingredient_id' => $ing->id,
                    'type' => 'restock',
                    'quantity' => $initialBatch,
                    'unit' => $item['unit'],
                    'balance_before' => 0,
                    'balance_after' => $initialBatch,
                    'reference_id' => 'PO-20260904-' . rand(101, 999),
                    'notes' => "Penerimaan PO pengadaan stok dari {$supplier}",
                    'created_by' => 'Dewi Sartika (Inventory Admin)',
                    'created_at' => Carbon::now()->subDays(5)->setTime(9, 15),
                ]);

                // Log 2: Pengurangan pesanan kasir 2 hari lalu
                if ($item['current_stock'] > 0) {
                    $deductBatch1 = round($item['current_stock'] * 0.2 + 2, 2);
                    $balanceAfter1 = round($initialBatch - $deductBatch1, 2);

                    IngredientStockLog::create([
                        'tenant_id' => $outlet->tenant_id,
                        'outlet_id' => $outlet->id,
                        'ingredient_id' => $ing->id,
                        'type' => 'order_deduct',
                        'quantity' => -$deductBatch1,
                        'unit' => $item['unit'],
                        'balance_before' => $initialBatch,
                        'balance_after' => $balanceAfter1,
                        'reference_id' => 'ORD-260907-' . str_pad(rand(10, 99), 4, '0', STR_PAD_LEFT),
                        'notes' => 'Pengurangan pemakaian otomatis pesanan kasir shift pagi-siang',
                        'created_by' => 'Sistem POS Kasir',
                        'created_at' => Carbon::now()->subDays(2)->setTime(14, 20),
                    ]);

                    // Log 3: Pengurangan pesanan kasir hari ini (beberapa jam lalu)
                    $deductBatch2 = round($balanceAfter1 - $item['current_stock'], 2);
                    if ($deductBatch2 > 0) {
                        IngredientStockLog::create([
                            'tenant_id' => $outlet->tenant_id,
                            'outlet_id' => $outlet->id,
                            'ingredient_id' => $ing->id,
                            'type' => 'order_deduct',
                            'quantity' => -$deductBatch2,
                            'unit' => $item['unit'],
                            'balance_before' => $balanceAfter1,
                            'balance_after' => $item['current_stock'],
                            'reference_id' => 'ORD-260909-' . str_pad(rand(1, 40), 4, '0', STR_PAD_LEFT),
                            'notes' => 'Pengurangan pemakaian otomatis pesanan kasir jam sibuk',
                            'created_by' => 'Sistem POS Kasir',
                            'created_at' => Carbon::now()->subHours(rand(1, 4)),
                        ]);
                    }
                }
            }

            // Tambahkan 1 log kalibrasi manual pada Biji Kopi Espresso Blend
            if (isset($createdIngredients['Biji Kopi Espresso Blend (70% Arabica + 30% Robusta)'])) {
                $esp = $createdIngredients['Biji Kopi Espresso Blend (70% Arabica + 30% Robusta)'];
                IngredientStockLog::create([
                    'tenant_id' => $outlet->tenant_id,
                    'outlet_id' => $outlet->id,
                    'ingredient_id' => $esp->id,
                    'type' => 'manual_adjustment',
                    'quantity' => -0.05,
                    'unit' => 'kg',
                    'balance_before' => 14.30,
                    'balance_after' => 14.25,
                    'reference_id' => 'CALIB-' . date('ymd'),
                    'notes' => 'Kalibrasi grinder & dial-in espresso pagi hari',
                    'created_by' => 'Budi Setiawan (Head Barista)',
                    'created_at' => Carbon::now()->subHours(5)->setTime(7, 45),
                ]);
            }

            // Tambahkan 1 log waste pada Cup Panas 8oz
            if (isset($createdIngredients['Cup Panas Double Wall 8oz Paper Cup + Lid'])) {
                $cupHot = $createdIngredients['Cup Panas Double Wall 8oz Paper Cup + Lid'];
                IngredientStockLog::create([
                    'tenant_id' => $outlet->tenant_id,
                    'outlet_id' => $outlet->id,
                    'ingredient_id' => $cupHot->id,
                    'type' => 'waste',
                    'quantity' => -5.00,
                    'unit' => 'pcs',
                    'balance_before' => 70.00,
                    'balance_after' => 65.00,
                    'reference_id' => 'WASTE-' . rand(100, 999),
                    'notes' => 'Kemasan cacat / penyok dari pembungkus',
                    'created_by' => 'Siti Nurhaliza (Barista)',
                    'created_at' => Carbon::now()->subDays(1)->setTime(16, 30),
                ]);
            }

            // =========================================================================
            // HUBUNGKAN RESEP KULINER REALISTIS KE MENU-MENU YANG ADA
            // =========================================================================
            $menus = MenuItem::where('outlet_id', $outlet->id)->get();
            foreach ($menus as $menu) {
                $name = strtolower($menu->name);

                if (str_contains($name, 'kopi kenangan') || str_contains($name, 'kenangan mantan')) {
                    $this->attachRecipe($menu, $createdIngredients, 'Biji Kopi Espresso Blend (70% Arabica + 30% Robusta)', 20); // 20g
                    $this->attachRecipe($menu, $createdIngredients, 'Susu Fresh Milk Pasteurisasi Greenfield', 120); // 120ml
                    $this->attachRecipe($menu, $createdIngredients, 'Sirup Gula Aren Murni Organik', 25); // 25ml
                    $this->attachRecipe($menu, $createdIngredients, 'Cup Plastik PET 16oz Sablon Logo + Lid Datar', 1);
                    $this->attachRecipe($menu, $createdIngredients, 'Sedotan Runcing Steril Bungkus Kertas', 1);
                } elseif (str_contains($name, 'caramel macchiato')) {
                    $this->attachRecipe($menu, $createdIngredients, 'Biji Kopi Espresso Blend (70% Arabica + 30% Robusta)', 20);
                    $this->attachRecipe($menu, $createdIngredients, 'Susu Fresh Milk Pasteurisasi Greenfield', 150);
                    $this->attachRecipe($menu, $createdIngredients, 'Sirup Vanilla Monin 700ml', 1);
                    $this->attachRecipe($menu, $createdIngredients, 'Sirup Salted Caramel Toffin 750ml', 1);
                    $this->attachRecipe($menu, $createdIngredients, 'Cup Plastik PET 16oz Sablon Logo + Lid Datar', 1);
                } elseif (str_contains($name, 'matcha espresso')) {
                    $this->attachRecipe($menu, $createdIngredients, 'Biji Kopi Espresso Blend (70% Arabica + 30% Robusta)', 20);
                    $this->attachRecipe($menu, $createdIngredients, 'Matcha Powder Uji Ceremonial Grade', 15);
                    $this->attachRecipe($menu, $createdIngredients, 'Susu Fresh Milk Pasteurisasi Greenfield', 140);
                    $this->attachRecipe($menu, $createdIngredients, 'Cup Plastik PET 16oz Sablon Logo + Lid Datar', 1);
                    $this->attachRecipe($menu, $createdIngredients, 'Sedotan Runcing Steril Bungkus Kertas', 1);
                } elseif (str_contains($name, 'spanish latte')) {
                    $this->attachRecipe($menu, $createdIngredients, 'Biji Kopi Espresso Blend (70% Arabica + 30% Robusta)', 20);
                    $this->attachRecipe($menu, $createdIngredients, 'Susu Fresh Milk Pasteurisasi Greenfield', 130);
                    $this->attachRecipe($menu, $createdIngredients, 'Krimer Kental Manis Frisian Flag Pouch 560g', 1);
                    $this->attachRecipe($menu, $createdIngredients, 'Cup Panas Double Wall 8oz Paper Cup + Lid', 1);
                } elseif (str_contains($name, 'americano')) {
                    $this->attachRecipe($menu, $createdIngredients, 'Biji Kopi Espresso Blend (70% Arabica + 30% Robusta)', 20);
                    $this->attachRecipe($menu, $createdIngredients, 'Cup Plastik PET 16oz Sablon Logo + Lid Datar', 1);
                    $this->attachRecipe($menu, $createdIngredients, 'Sedotan Runcing Steril Bungkus Kertas', 1);
                } elseif (str_contains($name, 'earl grey')) {
                    $this->attachRecipe($menu, $createdIngredients, 'Daun Teh Hitam Earl Grey Premium', 12);
                    $this->attachRecipe($menu, $createdIngredients, 'Susu UHT Full Cream Diamond', 160);
                    $this->attachRecipe($menu, $createdIngredients, 'Boba Tapioka Pearl Brown Sugar', 40);
                    $this->attachRecipe($menu, $createdIngredients, 'Cup Plastik PET 16oz Sablon Logo + Lid Datar', 1);
                    $this->attachRecipe($menu, $createdIngredients, 'Sedotan Runcing Steril Bungkus Kertas', 1);
                } elseif (str_contains($name, 'chocolate')) {
                    $this->attachRecipe($menu, $createdIngredients, 'Dark Chocolate Powder Belgia 70%', 30);
                    $this->attachRecipe($menu, $createdIngredients, 'Susu Fresh Milk Pasteurisasi Greenfield', 180);
                    $this->attachRecipe($menu, $createdIngredients, 'Cup Plastik PET 16oz Sablon Logo + Lid Datar', 1);
                    $this->attachRecipe($menu, $createdIngredients, 'Sedotan Runcing Steril Bungkus Kertas', 1);
                } elseif (str_contains($name, 'beef') || str_contains($name, 'black pepper')) {
                    $this->attachRecipe($menu, $createdIngredients, 'Daging Sapi US Shortplate Slice 1.5mm', 100);
                    $this->attachRecipe($menu, $createdIngredients, 'Beras Pandan Wangi Cianjur Super', 150);
                    $this->attachRecipe($menu, $createdIngredients, 'Saus Lada Hitam Black Pepper Saori', 30);
                    $this->attachRecipe($menu, $createdIngredients, 'Minyak Goreng SunCo Pouch 2 Liter', 15);
                    $this->attachRecipe($menu, $createdIngredients, 'Paper Bowl 650ml Rice Bowl + Lid Transparan', 1);
                } elseif (str_contains($name, 'french fries') || str_contains($name, 'truffle')) {
                    $this->attachRecipe($menu, $createdIngredients, 'Kentang Beku Shoestring Aviko 2.5kg', 150);
                    $this->attachRecipe($menu, $createdIngredients, 'Minyak Goreng SunCo Pouch 2 Liter', 50);
                    $this->attachRecipe($menu, $createdIngredients, 'Paper Bag Kraft Sablon Handle Tali', 1);
                } elseif (str_contains($name, 'tiramisu')) {
                    $this->attachRecipe($menu, $createdIngredients, 'Keju Oles Cream Cheese Anchor', 60);
                    $this->attachRecipe($menu, $createdIngredients, 'Biskuit Lotus Biscoff Crumb Repack', 30);
                    $this->attachRecipe($menu, $createdIngredients, 'Bubuk Kopi Robusta Dampit Fine Grind', 10);
                } elseif (str_contains($name, 'nasi goreng')) {
                    $this->attachRecipe($menu, $createdIngredients, 'Beras Pandan Wangi Cianjur Super', 150);
                    $this->attachRecipe($menu, $createdIngredients, 'Telur Ayam Negeri Fresh Grade A', 60);
                    $this->attachRecipe($menu, $createdIngredients, 'Minyak Goreng SunCo Pouch 2 Liter', 20);
                }
            }
        }

        $this->command->info('IngredientSeeder berhasil dieksekusi dengan 34 bahan baku realistis & dinamis!');
    }

    private function attachRecipe(MenuItem $menu, array $createdIngredients, string $ingredientName, float $qtyNeeded): void
    {
        if (isset($createdIngredients[$ingredientName])) {
            MenuItemRecipe::create([
                'menu_item_id' => $menu->id,
                'ingredient_id' => $createdIngredients[$ingredientName]->id,
                'quantity_needed' => $qtyNeeded,
            ]);
        }
    }
}
