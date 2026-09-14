<?php

namespace Database\Seeders;

use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Outlet;
use App\Models\Payment;
use App\Models\Table;
use App\Models\TableSession;
use App\Models\Tenant;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class CashierDisplaySeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::first();
        if (!$tenant) {
            $tenant = Tenant::create([
                'name' => 'Kopi Senja Utama',
                'slug' => 'kopi-senja',
                'email' => 'admin@kopisenja.id',
                'status' => 'active',
            ]);
        }

        $outlet = Outlet::where('tenant_id', $tenant->id)->first();
        if (!$outlet) {
            $outlet = Outlet::create([
                'tenant_id' => $tenant->id,
                'name' => 'Kopi Senja - Senopati',
                'slug' => 'kopi-senja-senopati',
                'address' => 'Jl. Senopati No. 45, Kebayoran Baru, Jakarta Selatan',
                'phone' => '081234567890',
                'is_active' => true,
            ]);
        }

        $cashier = User::where('email', 'kasir@kopisenopati.id')->first() ?? User::where('tenant_id', $tenant->id)->first();

        // 1. SEED 12 TABLES (Indoor 01-06, Outdoor 07-12)
        $tablesConfig = [
            ['num' => '01', 'capacity' => 2, 'session' => true, 'cust' => 'Dimas Aditya'],
            ['num' => '02', 'capacity' => 4, 'session' => false, 'cust' => null],
            ['num' => '03', 'capacity' => 4, 'session' => true, 'cust' => 'Sarah Putri'],
            ['num' => '04', 'capacity' => 6, 'session' => false, 'cust' => null],
            ['num' => '05', 'capacity' => 2, 'session' => false, 'cust' => null],
            ['num' => '06', 'capacity' => 4, 'session' => false, 'cust' => null],
            ['num' => '07', 'capacity' => 4, 'session' => false, 'cust' => null],
            ['num' => '08', 'capacity' => 6, 'session' => true, 'cust' => 'Rian & Co'],
            ['num' => '09', 'capacity' => 2, 'session' => false, 'cust' => null],
            ['num' => '10', 'capacity' => 8, 'session' => false, 'cust' => null],
            ['num' => '11', 'capacity' => 4, 'session' => false, 'cust' => null],
            ['num' => '12', 'capacity' => 4, 'session' => false, 'cust' => null],
        ];

        // Clean existing sessions
        TableSession::whereHas('table', fn($q) => $q->where('outlet_id', $outlet->id))->delete();

        $tables = [];
        foreach ($tablesConfig as $idx => $t) {
            $table = Table::updateOrCreate(
                ['tenant_id' => $tenant->id, 'outlet_id' => $outlet->id, 'table_number' => "Meja {$t['num']}"],
                [
                    'capacity' => $t['capacity'],
                    'qr_code_token' => "qr_senopati_{$t['num']}",
                    'is_active' => true,
                ]
            );
            $tables["Meja {$t['num']}"] = $table;

            if ($t['session']) {
                TableSession::create([
                    'table_id' => $table->id,
                    'status' => 'active',
                    'customer_identifier' => $t['cust'],
                    'opened_at' => Carbon::now()->subMinutes(15 + ($idx * 8)),
                ]);
            }
        }

        // 2. SEED MENU CATEGORIES
        $categoriesData = [
            ['name' => 'Kopi Signature', 'sort' => 1],
            ['name' => 'Non-Kopi & Teh', 'sort' => 2],
            ['name' => 'Makanan Utama', 'sort' => 3],
            ['name' => 'Pastry & Camilan', 'sort' => 4],
            ['name' => 'Dessert', 'sort' => 5],
        ];

        $categories = [];
        foreach ($categoriesData as $c) {
            $cat = MenuCategory::updateOrCreate(
                ['tenant_id' => $tenant->id, 'outlet_id' => $outlet->id, 'name' => $c['name']],
                ['sort_order' => $c['sort'], 'is_active' => true]
            );
            $categories[$c['name']] = $cat;
        }

        // 3. SEED MENU ITEMS
        $menuItemsList = [
            [
                'cat' => 'Kopi Signature',
                'name' => 'Kopi Kenangan Mantan',
                'desc' => 'Espresso house blend dipadu susu segar pilihan dan gula aren asli.',
                'price' => 18000,
                'img' => 'https://images.unsplash.com/photo-1541167760496-1628856ab772?w=500&auto=format&fit=crop&q=80',
            ],
            [
                'cat' => 'Kopi Signature',
                'name' => 'Caramel Macchiato',
                'desc' => 'Espresso bold dengan susu creamy dan sirup karamel bakar khas Italia.',
                'price' => 26000,
                'img' => 'https://images.unsplash.com/photo-1572442388796-11668a67e53d?w=500&auto=format&fit=crop&q=80',
            ],
            [
                'cat' => 'Kopi Signature',
                'name' => 'Americano On The Rocks',
                'desc' => 'Double espresso shot disajikan dengan es batu kristal segar.',
                'price' => 16000,
                'img' => 'https://images.unsplash.com/photo-1514432324607-a09d9b4aefdd?w=500&auto=format&fit=crop&q=80',
            ],
            [
                'cat' => 'Kopi Signature',
                'name' => 'Spanish Latte Cinnamon',
                'desc' => 'Susu kental manis khas Spanyol dengan taburan kayu manis organik.',
                'price' => 24000,
                'img' => 'https://images.unsplash.com/photo-1534778101976-62847782c213?w=500&auto=format&fit=crop&q=80',
            ],
            [
                'cat' => 'Non-Kopi & Teh',
                'name' => 'Matcha Espresso Latte',
                'desc' => 'Matcha premium Uji Kyoto dengan layer susu dan sentuhan espresso.',
                'price' => 28000,
                'img' => 'https://images.unsplash.com/photo-1536256263959-770b48d82b0a?w=500&auto=format&fit=crop&q=80',
            ],
            [
                'cat' => 'Non-Kopi & Teh',
                'name' => 'Earl Grey Milk Tea',
                'desc' => 'Teh hitam beraroma bergamot dengan susu segar dan brown sugar.',
                'price' => 22000,
                'img' => 'https://images.unsplash.com/photo-1576092768241-dec231879fc3?w=500&auto=format&fit=crop&q=80',
            ],
            [
                'cat' => 'Non-Kopi & Teh',
                'name' => 'Signature Chocolate Ice',
                'desc' => 'Cokelat Belgia pekat dengan susu cair dan topping serpihan cokelat.',
                'price' => 25000,
                'img' => 'https://images.unsplash.com/photo-1544787219-7f47ccb76574?w=500&auto=format&fit=crop&q=80',
            ],
            [
                'cat' => 'Makanan Utama',
                'name' => 'Nasi Goreng Kampung Spesial',
                'desc' => 'Nasi goreng bumbu terasi tradisional dengan telur mata sapi, sate ayam & kerupuk.',
                'price' => 38000,
                'img' => 'https://images.unsplash.com/photo-1603133872878-684f208fb84b?w=500&auto=format&fit=crop&q=80',
            ],
            [
                'cat' => 'Makanan Utama',
                'name' => 'Mie Goreng Seafood',
                'desc' => 'Mie telur kenyal dengan udang, cumi segar, telur orak-arik dan sayuran.',
                'price' => 42000,
                'img' => 'https://images.unsplash.com/photo-1612927601601-6638404737ce?w=500&auto=format&fit=crop&q=80',
            ],
            [
                'cat' => 'Makanan Utama',
                'name' => 'Beef Black Pepper Rice Bowl',
                'desc' => 'Daging sapi iris empuk saus lada hitam khas oriental di atas nasi hangat.',
                'price' => 45000,
                'img' => 'https://images.unsplash.com/photo-1546069901-ba9599a7e63c?w=500&auto=format&fit=crop&q=80',
            ],
            [
                'cat' => 'Pastry & Camilan',
                'name' => 'Croissant Butter Crispy',
                'desc' => 'Pastry Prancis berlayer renyah dengan mentega butter murni.',
                'price' => 22000,
                'img' => 'https://images.unsplash.com/photo-1555507036-ab1f4038808a?w=500&auto=format&fit=crop&q=80',
            ],
            [
                'cat' => 'Pastry & Camilan',
                'name' => 'French Fries Truffle Oil',
                'desc' => 'Kentang goreng renyah dengan minyak truffle aromatik dan taburan parsley parmesan.',
                'price' => 28000,
                'img' => 'https://images.unsplash.com/photo-1573080496219-bb080dd4f877?w=500&auto=format&fit=crop&q=80',
            ],
            [
                'cat' => 'Pastry & Camilan',
                'name' => 'Cinnamon Roll Glaze',
                'desc' => 'Roti gulung kayu manis lembut dengan topping cream cheese glaze meleleh.',
                'price' => 24000,
                'img' => 'https://images.unsplash.com/photo-1509440159596-0249088772ff?w=500&auto=format&fit=crop&q=80',
            ],
            [
                'cat' => 'Dessert',
                'name' => 'Classic Tiramisu Jar',
                'desc' => 'Ladyfinger celup espresso dengan krim mascarpone dan bubuk kakao murni.',
                'price' => 32000,
                'img' => 'https://images.unsplash.com/photo-1571877227200-a0d98ea607e9?w=500&auto=format&fit=crop&q=80',
            ],
        ];

        $menuItems = [];
        foreach ($menuItemsList as $m) {
            $item = MenuItem::updateOrCreate(
                ['tenant_id' => $tenant->id, 'outlet_id' => $outlet->id, 'name' => $m['name']],
                [
                    'category_id' => $categories[$m['cat']]->id,
                    'description' => $m['desc'],
                    'base_price' => $m['price'],
                    'image_url' => $m['img'],
                    'is_available' => true,
                ]
            );
            $menuItems[$m['name']] = $item;
        }

    }
}
