<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create Super Admin role with all permissions
        $superAdminRole = Role::create([
            'name' => 'Super Admin',
            'is_protected' => true,
        ]);
        $superAdminRole->givePermissionTo(Permission::all());

        // Create Student role with basic permissions
        $studentRole = Role::create([
            'name' => 'Student',
            'is_protected' => true,
        ]);
        $studentRole->givePermissionTo([
            // Basic viewing permissions
            'view.course',
            'view.level',
            'view.lesson',
            'view.slide',
            'view.term',
            'view.trophy',
            'download.receipt',
        ]);

        $this->command->info('Roles seeded successfully!');
        $this->command->info('Created roles: Super Admin, Student');
    }
}
