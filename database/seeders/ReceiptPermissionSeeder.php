<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class ReceiptPermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Create new permissions
        $permissions = [
            'manage.receipt',
            'download.receipt',
        ];

        foreach ($permissions as $permission) {
            // Check if permission exists before creating
            if (!Permission::where('name', $permission)->exists()) {
                Permission::create(['name' => $permission]);
            }
        }

        // Assign permissions to roles
        $this->assignPermissionsToRoles();

        $this->command->info('Receipt permissions seeded successfully!');
    }

    /**
     * Assign permissions to existing roles
     */
    private function assignPermissionsToRoles(): void
    {
        // Super admin already has all permissions

        // Admin
        if ($admin = Role::where('name', 'admin')->first()) {
            $admin->givePermissionTo('download.receipt');
        }

        // Analytics Manager
        if ($analyticsManager = Role::where('name', 'analytics_manager')->first()) {
            $analyticsManager->givePermissionTo('download.receipt');
        }

        // Customer Support
        if ($customerSupport = Role::where('name', 'customer_support')->first()) {
            $customerSupport->givePermissionTo([
                'view.payment',
                'download.receipt'
            ]);
        }

        // Finance Manager
        if ($financeManager = Role::where('name', 'finance_manager')->first()) {
            $financeManager->givePermissionTo([
                'manage.receipt',
                'download.receipt'
            ]);
        }

        // Student
        if ($student = Role::where('name', 'student')->first()) {
            $student->givePermissionTo('download.receipt');
        }
    }
}
