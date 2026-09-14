<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    public function run(): void
    {
        Plan::updateOrCreate(
            ['code' => 'basic'],
            [
                'name' => 'Basic Plan',
                'price_per_outlet_monthly' => 99000,
                'max_tables_per_outlet' => 10,
                'max_users_per_outlet' => 3,
                'max_outlets' => 1,
                'is_active' => true,
            ]
        );

        Plan::updateOrCreate(
            ['code' => 'pro'],
            [
                'name' => 'Pro Plan',
                'price_per_outlet_monthly' => 199000,
                'max_tables_per_outlet' => null, // unlimited
                'max_users_per_outlet' => null, // unlimited
                'max_outlets' => null, // unlimited
                'is_active' => true,
            ]
        );
    }
}
