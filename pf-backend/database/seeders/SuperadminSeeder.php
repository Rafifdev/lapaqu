<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class SuperadminSeeder extends Seeder
{
    public function run(): void
    {
        $superadmin = User::firstOrCreate(
            ['email' => 'admin@lapaqu.id'],
            [
                'name' => 'Platform Superadmin',
                'password' => Hash::make('LapaquAdmin2026!'),
                'is_active' => true,
            ]
        );

        $superadmin->assignRole('superadmin');

        // Test Superadmin account for OTP email testing
        $testAdmin = User::firstOrCreate(
            ['email' => 'tolebot3@gmail.com'],
            [
                'name' => 'Superadmin (Tolebot)',
                'password' => Hash::make('LapaquAdmin2026!'),
                'is_active' => true,
            ]
        );

        $testAdmin->assignRole('superadmin');
    }
}
