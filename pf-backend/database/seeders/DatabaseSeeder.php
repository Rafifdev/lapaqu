<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RoleAndPermissionSeeder::class,
            SuperadminSeeder::class,
            PlanSeeder::class,
            DemoTenantSeeder::class,
            MenuSeeder::class,
            IngredientCategorySeeder::class,
            IngredientSeeder::class,
            TableSeeder::class,
            OrderSeeder::class,
            CashierDisplaySeeder::class,
        ]);
    }
}
