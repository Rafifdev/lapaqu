<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleAndPermissionSeeder extends Seeder
{
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Create Permissions
        $permissions = [
            // Tenant management (Platform Superadmin)
            'manage-tenants',
            'manage-plans',
            'manage-platform-billing',
            'view-audit-logs',
            'approve-refunds',

            // Tenant Owner
            'manage-outlets',
            'manage-tables',
            'manage-menu',
            'manage-staff',
            'view-reports',
            'request-refund',
            'manage-tenant-billing',

            // Kasir
            'create-orders',
            'view-orders',
            'process-payments',
            'void-order-items',

            // Kitchen Staff
            'view-kds',
            'update-kitchen-status',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'sanctum']);
        }

        // Roles & Assign Permissions
        $superadminWeb = Role::firstOrCreate(['name' => 'superadmin', 'guard_name' => 'web']);
        $superadminSanctum = Role::firstOrCreate(['name' => 'superadmin', 'guard_name' => 'sanctum']);
        $superadminWeb->syncPermissions(Permission::where('guard_name', 'web')->get());
        $superadminSanctum->syncPermissions(Permission::where('guard_name', 'sanctum')->get());

        $ownerWeb = Role::firstOrCreate(['name' => 'owner', 'guard_name' => 'web']);
        $ownerSanctum = Role::firstOrCreate(['name' => 'owner', 'guard_name' => 'sanctum']);
        $ownerPermissions = [
            'manage-outlets',
            'manage-tables',
            'manage-menu',
            'manage-staff',
            'view-reports',
            'request-refund',
            'manage-tenant-billing',
            'create-orders',
            'view-orders',
            'process-payments',
            'void-order-items',
            'view-kds',
            'update-kitchen-status',
        ];
        $ownerWeb->syncPermissions(Permission::where('guard_name', 'web')->whereIn('name', $ownerPermissions)->get());
        $ownerSanctum->syncPermissions(Permission::where('guard_name', 'sanctum')->whereIn('name', $ownerPermissions)->get());

        // Store Manager (Penanggung Jawab Cabang)
        $managerWeb = Role::firstOrCreate(['name' => 'store_manager', 'guard_name' => 'web']);
        $managerSanctum = Role::firstOrCreate(['name' => 'store_manager', 'guard_name' => 'sanctum']);
        $managerPermissions = [
            'manage-tables',
            'manage-menu',
            'manage-staff',
            'view-reports',
            'request-refund',
            'create-orders',
            'view-orders',
            'process-payments',
            'void-order-items',
            'view-kds',
            'update-kitchen-status',
        ];
        $managerWeb->syncPermissions(Permission::where('guard_name', 'web')->whereIn('name', $managerPermissions)->get());
        $managerSanctum->syncPermissions(Permission::where('guard_name', 'sanctum')->whereIn('name', $managerPermissions)->get());

        $kasirWeb = Role::firstOrCreate(['name' => 'kasir', 'guard_name' => 'web']);
        $kasirSanctum = Role::firstOrCreate(['name' => 'kasir', 'guard_name' => 'sanctum']);
        $kasirPermissions = [
            'create-orders',
            'view-orders',
            'process-payments',
            'void-order-items',
            'request-refund',
        ];
        $kasirWeb->syncPermissions(Permission::where('guard_name', 'web')->whereIn('name', $kasirPermissions)->get());
        $kasirSanctum->syncPermissions(Permission::where('guard_name', 'sanctum')->whereIn('name', $kasirPermissions)->get());

        $kitchenWeb = Role::firstOrCreate(['name' => 'kitchen_staff', 'guard_name' => 'web']);
        $kitchenSanctum = Role::firstOrCreate(['name' => 'kitchen_staff', 'guard_name' => 'sanctum']);
        $kitchenPermissions = [
            'view-kds',
            'update-kitchen-status',
        ];
        $kitchenWeb->syncPermissions(Permission::where('guard_name', 'web')->whereIn('name', $kitchenPermissions)->get());
        $kitchenSanctum->syncPermissions(Permission::where('guard_name', 'sanctum')->whereIn('name', $kitchenPermissions)->get());
    }
}
