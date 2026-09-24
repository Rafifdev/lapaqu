<?php

namespace Database\Seeders;

use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\MenuItemVariantGroup;
use App\Models\MenuItemVariantOption;
use App\Models\Outlet;
use App\Models\Tenant;
use Illuminate\Database\Seeder;

class MenuSeeder extends Seeder
{
    public function run(): void
    {
        $outlets = Outlet::all();
        if ($outlets->isEmpty()) return;

        $categories = [
            ['name' => 'Kopi Signature', 'sort_order' => 1],
            ['name' => 'Non-Kopi & Teh', 'sort_order' => 2],
            ['name' => 'Makanan Utama', 'sort_order' => 3],
            ['name' => 'Pastry & Camilan', 'sort_order' => 4],
            ['name' => 'Dessert', 'sort_order' => 5],
        ];

        foreach ($outlets as $outlet) {
            $tenant = $outlet->tenant;
            if (!$tenant) continue;

            $categoryModels = [];
            foreach ($categories as $cat) {
                $categoryModels[$cat['name']] = MenuCategory::firstOrCreate(
                    ['tenant_id' => $tenant->id, 'outlet_id' => $outlet->id, 'name' => $cat['name']],
                    ['sort_order' => $cat['sort_order'], 'is_active' => true]
                );
            }

        // Menu Items Data
        $items = [
            [
                'name' => 'Kopi Kenangan Mantan',
                'category' => 'Kopi Signature',
                'description' => 'Espresso dengan susu segar dan gula aren murni pilihan',
                'base_price' => 18000,
                'image_url' => 'https://images.unsplash.com/photo-1541167760496-1628856ab772?auto=format&fit=crop&w=400&q=80',
                'variants' => [
                    [
                        'name' => 'Suhu',
                        'is_required' => true,
                        'options' => [
                            ['name' => 'Iced (Dingin)', 'price_modifier' => 0],
                            ['name' => 'Hot (Panas)', 'price_modifier' => 0],
                        ],
                    ],
                    [
                        'name' => 'Level Gula',
                        'is_required' => true,
                        'options' => [
                            ['name' => 'Normal Sugar (100%)', 'price_modifier' => 0],
                            ['name' => 'Less Sugar (50%)', 'price_modifier' => 0],
                            ['name' => 'No Sugar (0%)', 'price_modifier' => 0],
                        ],
                    ],
                    [
                        'name' => 'Extra Topping',
                        'is_required' => false,
                        'max_selection' => 2,
                        'options' => [
                            ['name' => 'Boba Brown Sugar', 'price_modifier' => 4000],
                            ['name' => 'Grass Jelly', 'price_modifier' => 3000],
                            ['name' => 'Espresso Shot', 'price_modifier' => 5000],
                        ],
                    ],
                ],
            ],
            [
                'name' => 'Matcha Espresso Latte',
                'category' => 'Kopi Signature',
                'description' => 'Perpaduan bubuk matcha premium Jepang dengan shot espresso dan susu lembut',
                'base_price' => 28000,
                'image_url' => 'https://images.unsplash.com/photo-1536256263959-770b48d82b0a?auto=format&fit=crop&w=400&q=80',
                'variants' => [
                    [
                        'name' => 'Suhu',
                        'is_required' => true,
                        'options' => [
                            ['name' => 'Iced (Dingin)', 'price_modifier' => 0],
                            ['name' => 'Hot (Panas)', 'price_modifier' => 0],
                        ],
                    ],
                ],
            ],
            [
                'name' => 'Caramel Macchiato',
                'category' => 'Kopi Signature',
                'description' => 'Espresso bold berpadu susu creamy dan drizzle saus karamel premium',
                'base_price' => 26000,
                'image_url' => 'https://images.unsplash.com/photo-1485808191679-5f86510681a2?auto=format&fit=crop&w=400&q=80',
                'variants' => [],
            ],
            [
                'name' => 'Earl Grey Milk Tea',
                'category' => 'Non-Kopi & Teh',
                'description' => 'Teh hitam aromatik Earl Grey dengan aroma bergamot dan susu segar',
                'base_price' => 20000,
                'image_url' => 'https://images.unsplash.com/photo-1576092768241-dec231879fc3?auto=format&fit=crop&w=400&q=80',
                'variants' => [
                    [
                        'name' => 'Level Gula',
                        'is_required' => true,
                        'options' => [
                            ['name' => 'Normal Sugar (100%)', 'price_modifier' => 0],
                            ['name' => 'Less Sugar (50%)', 'price_modifier' => 0],
                        ],
                    ],
                ],
            ],
            [
                'name' => 'Signature Chocolate Ice',
                'category' => 'Non-Kopi & Teh',
                'description' => 'Cokelat Belgia pekat disajikan dingin dengan whipped cream lembut',
                'base_price' => 24000,
                'image_url' => 'https://images.unsplash.com/photo-1542990253-0d0f5be5f0ed?auto=format&fit=crop&w=400&q=80',
                'variants' => [],
            ],
            [
                'name' => 'Nasi Goreng Kampung Spesial',
                'category' => 'Makanan Utama',
                'description' => 'Nasi goreng bumbu tradisional dengan telur mata sapi, ayam suwir, dan kerupuk',
                'base_price' => 32000,
                'image_url' => 'https://images.unsplash.com/photo-1603133872878-684f208fb84b?auto=format&fit=crop&w=400&q=80',
                'variants' => [
                    [
                        'name' => 'Level Pedas',
                        'is_required' => true,
                        'options' => [
                            ['name' => 'Tidak Pedas', 'price_modifier' => 0],
                            ['name' => 'Sedang', 'price_modifier' => 0],
                            ['name' => 'Pedas Banget', 'price_modifier' => 0],
                        ],
                    ],
                ],
            ],
            [
                'name' => 'Mie Goreng Seafood',
                'category' => 'Makanan Utama',
                'description' => 'Mie kenyal ditumis dengan udang segar, cumi, dan sayuran renyah',
                'base_price' => 35000,
                'image_url' => 'https://images.unsplash.com/photo-1585032226651-759b368d7246?auto=format&fit=crop&w=400&q=80',
                'variants' => [],
            ],
            [
                'name' => 'Croissant Butter Crispy',
                'category' => 'Pastry & Camilan',
                'description' => 'Pastry renyah berlapis dengan aroma butter Prancis yang harum',
                'base_price' => 22000,
                'image_url' => 'https://images.unsplash.com/photo-1555507036-ab1f4038808a?auto=format&fit=crop&w=400&q=80',
                'variants' => [],
            ],
            [
                'name' => 'French Fries Truffle Oil',
                'category' => 'Pastry & Camilan',
                'description' => 'Kentang goreng gurih disajikan dengan aroma minyak truffle dan keju parmesan',
                'base_price' => 25000,
                'image_url' => 'https://images.unsplash.com/photo-1573080496219-bb080dd4f877?auto=format&fit=crop&w=400&q=80',
                'variants' => [],
            ],
            [
                'name' => 'Cinnamon Roll Glaze',
                'category' => 'Dessert',
                'description' => 'Roti kayu manis empuk dengan topping cream cheese frosting manis gurih',
                'base_price' => 26000,
                'image_url' => 'https://images.unsplash.com/photo-1509440159596-0249088772ff?auto=format&fit=crop&w=400&q=80',
                'variants' => [],
            ],
        ];

        foreach ($items as $itemData) {
            $cat = $categoryModels[$itemData['category']] ?? null;
            if (!$cat) continue;

            $menuItem = MenuItem::updateOrCreate(
                ['tenant_id' => $tenant->id, 'outlet_id' => $outlet->id, 'name' => $itemData['name']],
                [
                    'category_id' => $cat->id,
                    'description' => $itemData['description'],
                    'base_price' => $itemData['base_price'],
                    'image_url' => $itemData['image_url'],
                    'is_available' => true,
                ]
            );

            if (!empty($itemData['variants'])) {
                foreach ($itemData['variants'] as $vgData) {
                    $group = MenuItemVariantGroup::firstOrCreate(
                        ['menu_item_id' => $menuItem->id, 'name' => $vgData['name']],
                        [
                            'is_required' => $vgData['is_required'] ?? false,
                            'min_selection' => ($vgData['is_required'] ?? false) ? 1 : 0,
                            'max_selection' => $vgData['max_selection'] ?? 1,
                        ]
                    );

                    foreach ($vgData['options'] as $optData) {
                        MenuItemVariantOption::firstOrCreate(
                            ['variant_group_id' => $group->id, 'name' => $optData['name']],
                            [
                                'price_modifier' => $optData['price_modifier'] ?? 0,
                                'is_available' => true,
                            ]
                        );
                    }
                }
            }
        }
        }
    }
}
