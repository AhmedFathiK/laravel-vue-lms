<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;

class TrashPermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Create trash management permissions
        $permissions = [
            'view.trash',
            'restore.trash',
            'delete.trash',
        ];

        // Create permissions if they don't exist
        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        $this->command->info('Trash permissions seeded successfully!');
    }
}
