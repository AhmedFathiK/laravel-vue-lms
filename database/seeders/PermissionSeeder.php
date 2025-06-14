<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;

class PermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Create all permissions
        $permissions = [
            // User Management
            'view.user',
            'create.user',
            'edit.user',
            'delete.user',
            'ban.user',
            'assign_role.user',

            // Role Management
            'view.role',
            'create.role',
            'edit.role',
            'delete.role',
            'assign_permission.role',

            // Course Management
            'view.course',
            'create.course',
            'edit.course',
            'delete.course',

            // Level Management
            'view.level',
            'create.level',
            'edit.level',
            'delete.level',
            'unlock.level',

            // Lesson Management
            'view.lesson',
            'create.lesson',
            'edit.lesson',
            'delete.lesson',
            'configure.lesson',
            'add_video.lesson',

            // Slide Management
            'view.slide',
            'create.slide',
            'edit.slide',
            'delete.slide',
            'reorder.slide',

            // Term / Concept Management
            'view.term',
            'create.term',
            'edit.term',
            'delete.term',
            'translate.term',
            'link.term',
            'configure_revision.term',

            // Assessment System
            'view questions',
            'create questions',
            'edit questions',
            'delete questions',
            'view exams',
            'create exams',
            'edit exams',
            'delete exams',
            'view exam sections',
            'create exam sections',
            'edit exam sections',
            'delete exam sections',
            'grade exams',

            // Placement Tests
            'view.placement_test',
            'create.placement_test',
            'edit.placement_test',
            'assign.placement_test',

            // Gamification
            'view.trophy',
            'manage.trophy',
            'assign.trophy',

            // Analytics & Stats
            'view.user_stat',
            'view.course_stat',
            'analyze_weakness.user_stat',

            // Subscriptions & Payments
            'view.payment',
            'manage.subscription',
            'configure.pricing',
            'manage.receipt',
            'download.receipt',

            // Settings & Localization
            'access.setting',
            'manage.translation',
            'manage.localization',

            // Trash Management
            'view.trash',
            'restore.trash',
            'delete.trash',

            // Admin Panel
            'access.admin_panel',
        ];

        // Create permissions
        foreach ($permissions as $permission) {
            Permission::create(['name' => $permission]);
        }

        $this->command->info('Permissions seeded successfully!');
    }
}
