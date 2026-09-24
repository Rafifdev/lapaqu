<?php

namespace Database\Seeders;

use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\MenuItemVariantGroup;
use App\Models\MenuItemVariantOption;
use App\Models\Outlet;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Table;
use App\Models\Tenant;
use App\Models\TenantPaymentAccount;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoTenantSeeder extends Seeder
{
    public function run(): void
    {
        $plan = Plan::where('code', 'basic')->first() ?? Plan::create([
            'code' => 'basic',
            'name' => 'Basic Plan',
            'price_per_outlet_monthly' => 99000,
            'max_tables_per_outlet' => 10,
            'max_users_per_outlet' => 3,
            'max_outlets' => 1,
            'is_active' => true,
        ]);

        $tenant = Tenant::firstOrCreate(
            ['subdomain' => 'kopi-senopati'],
            [
                'name' => 'Kopi Kenangan Senopati',
                'status' => 'active',
                'trial_ends_at' => now()->addDays(14),
            ]
        );

        Subscription::firstOrCreate(
            ['tenant_id' => $tenant->id],
            [
                'plan_id' => $plan->id,
                'status' => 'active',
                'active_outlets_count' => 1,
                'current_period_start' => now(),
                'current_period_end' => now()->addMonth(),
                'next_billing_date' => now()->addMonth(),
            ]
        );

        TenantPaymentAccount::firstOrCreate(
            ['tenant_id' => $tenant->id],
            [
                'xendit_sub_account_id' => 'xnd_sub_demo_senopati_123',
                'bank_code' => 'BCA',
                'bank_account_number' => '8830123456',
                'bank_account_holder_name' => 'PT Kopi Senopati Indonesia',
                'is_active' => true,
            ]
        );

        $outlet = Outlet::firstOrCreate(
            ['tenant_id' => $tenant->id, 'name' => 'Cabang Senopati Utama'],
            [
                'address' => 'Jl. Senopati No. 45, Kebayoran Baru, Jakarta Selatan',
                'phone' => '021-5551234',
                'timezone' => 'Asia/Jakarta',
                'is_active' => true,
            ]
        );

        // Seed Tables
        for ($i = 1; $i <= 5; $i++) {
            $num = sprintf('%02d', $i);
            Table::firstOrCreate(
                ['tenant_id' => $tenant->id, 'outlet_id' => $outlet->id, 'table_number' => "Meja {$num}"],
                [
                    'capacity' => 4,
                    'qr_code_token' => "qr_senopati_{$num}",
                    'is_active' => true,
                ]
            );
        }

        // Seed Users
        $owner = User::firstOrCreate(
            ['email' => 'owner@kopisenopati.id'],
            [
                'tenant_id' => $tenant->id,
                'outlet_id' => $outlet->id,
                'name' => 'Budi Santoso (Owner)',
                'password' => Hash::make('RahasiaKopi123!'),
                'phone' => '081234567890',
                'is_active' => true,
            ]
        );
        $owner->syncRoles(['owner']);
        // Seed Store Manager
        $manager = User::firstOrCreate(
            ['email' => 'manager@kopisenopati.id'],
            [
                'tenant_id' => $tenant->id,
                'outlet_id' => $outlet->id,
                'name' => 'Dimas (Store Manager)',
                'password' => Hash::make('RahasiaKopi123!'),
                'pin' => Hash::make('123456'),
                'phone' => '081234567893',
                'is_active' => true,
            ]
        );
        $manager->syncRoles(['store_manager']);


        $kasir = User::firstOrCreate(
            ['email' => 'kasir@kopisenopati.id'],
            [
                'tenant_id' => $tenant->id,
                'outlet_id' => $outlet->id,
                'name' => 'Siti Kasir',
                'password' => Hash::make('RahasiaKopi123!'),
                'pin' => Hash::make('123456'),
                'phone' => '081234567891',
                'is_active' => true,
            ]
        );
        $kasir->syncRoles(['kasir']);

        $kitchen = User::firstOrCreate(
            ['email' => 'kitchen@kopisenopati.id'],
            [
                'tenant_id' => $tenant->id,
                'outlet_id' => $outlet->id,
                'name' => 'Chef Arnold (Kitchen)',
                'password' => Hash::make('RahasiaKopi123!'),
                'pin' => Hash::make('123456'),
                'phone' => '081234567892',
                'is_active' => true,
            ]
        );
        $kitchen->syncRoles(['kitchen_staff']);

        // Seed Menu Categories & Items
        $catKopi = MenuCategory::firstOrCreate(
            ['tenant_id' => $tenant->id, 'outlet_id' => $outlet->id, 'name' => 'Kopi Signature'],
            ['sort_order' => 1, 'is_active' => true]
        );

        $catNonKopi = MenuCategory::firstOrCreate(
            ['tenant_id' => $tenant->id, 'outlet_id' => $outlet->id, 'name' => 'Non-Kopi & Teh'],
            ['sort_order' => 2, 'is_active' => true]
        );

        $catSnack = MenuCategory::firstOrCreate(
            ['tenant_id' => $tenant->id, 'outlet_id' => $outlet->id, 'name' => 'Makanan & Camilan'],
            ['sort_order' => 3, 'is_active' => true]
        );

        // Menu 1: Kopi Kenangan Mantan
        $menu1 = MenuItem::firstOrCreate(
            ['tenant_id' => $tenant->id, 'outlet_id' => $outlet->id, 'name' => 'Kopi Kenangan Mantan'],
            [
                'category_id' => $catKopi->id,
                'description' => 'Espresso dengan susu segar dan gula aren murni pilihan',
                'base_price' => 18000,
                'is_available' => true,
            ]
        );

        $varSugar = MenuItemVariantGroup::firstOrCreate(
            ['menu_item_id' => $menu1->id, 'name' => 'Level Gula'],
            ['is_required' => true, 'min_selection' => 1, 'max_selection' => 1]
        );
        MenuItemVariantOption::firstOrCreate(
            ['variant_group_id' => $varSugar->id, 'name' => 'Normal Sugar (100%)'],
            ['price_modifier' => 0, 'is_available' => true]
        );
        MenuItemVariantOption::firstOrCreate(
            ['variant_group_id' => $varSugar->id, 'name' => 'Less Sugar (50%)'],
            ['price_modifier' => 0, 'is_available' => true]
        );

        $varTopping = MenuItemVariantGroup::firstOrCreate(
            ['menu_item_id' => $menu1->id, 'name' => 'Extra Topping'],
            ['is_required' => false, 'min_selection' => 0, 'max_selection' => 2]
        );
        MenuItemVariantOption::firstOrCreate(
            ['variant_group_id' => $varTopping->id, 'name' => 'Boba Brown Sugar'],
            ['price_modifier' => 4000, 'is_available' => true]
        );
        MenuItemVariantOption::firstOrCreate(
            ['variant_group_id' => $varTopping->id, 'name' => 'Grass Jelly (Cincau)'],
            ['price_modifier' => 3000, 'is_available' => true]
        );

        // Menu 2: Matcha Espresso Latte
        MenuItem::firstOrCreate(
            ['tenant_id' => $tenant->id, 'outlet_id' => $outlet->id, 'name' => 'Matcha Espresso Latte'],
            [
                'category_id' => $catKopi->id,
                'description' => 'Perpaduan bubuk matcha premium Jepang dengan shot espresso',
                'base_price' => 28000,
                'is_available' => true,
            ]
        );

        // Menu 3: Earl Grey Milk Tea
        MenuItem::firstOrCreate(
            ['tenant_id' => $tenant->id, 'outlet_id' => $outlet->id, 'name' => 'Earl Grey Milk Tea'],
            [
                'category_id' => $catNonKopi->id,
                'description' => 'Teh hitam aromatik Earl Grey dengan susu segar creamy',
                'base_price' => 20000,
                'is_available' => true,
            ]
        );

        // Menu 4: Croissant Butter
        MenuItem::firstOrCreate(
            ['tenant_id' => $tenant->id, 'outlet_id' => $outlet->id, 'name' => 'Croissant Butter Crispy'],
            [
                'category_id' => $catSnack->id,
                'description' => 'Pastry renyah dengan aroma butter Prancis yang harum',
                'base_price' => 22000,
                'is_available' => true,
            ]
        );

        // Menu 5: French Fries Truffle
        MenuItem::firstOrCreate(
            ['tenant_id' => $tenant->id, 'outlet_id' => $outlet->id, 'name' => 'French Fries Truffle Oil'],
            [
                'category_id' => $catSnack->id,
                'description' => 'Kentang goreng gurih disajikan dengan aroma minyak truffle dan keju parmesan',
                'base_price' => 25000,
                'is_available' => true,
            ]
        );
    }
}
