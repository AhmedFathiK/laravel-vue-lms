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
            'view.users',
            'create.users',
            'edit.users',
            'delete.users',
            'ban.users',
            'assign_role.users',

            // Role Management
            'view.roles',
            'create.roles',
            'edit.roles',
            'delete.roles',
            'assign_permission.roles',

            // Course Management
            'view.courses',
            'create.courses',
            'edit.courses',
            'delete.courses',

            // Course Category Management (New)
            'view.course_category',
            'create.course_category',
            'update.course_category',
            'delete.course_category',

            // Level Management
            'view.levels',
            'create.levels',
            'edit.levels',
            'delete.levels',
            'unlock.levels',

            // Lesson Management
            'view.lessons',
            'create.lessons',
            'edit.lessons',
            'delete.lessons',
            'configure.lessons',
            'add_video.lessons',

            // Slide Management
            'view.slides',
            'create.slides',
            'edit.slides',
            'delete.slides',
            'reorder.slides',

            // Term / Concept Management
            'view.terms',
            'create.terms',
            'edit.terms',
            'delete.terms',
            'translate.terms',
            'link.terms',
            'configure_revision.terms',

            // Assessment System
            'view.questions',
            'create.questions',
            'edit.questions',
            'delete.questions',
            'view.exams',
            'create.exams',
            'edit.exams',
            'delete.exams',
            'view.exam_sections',
            'create.exam_sections',
            'edit.exam_sections',
            'delete.exam_sections',
            'grade.exams',

            // Placement Tests
            'view.placement_tests',
            'create.placement_tests',
            'edit.placement_tests',
            'assign.placement_tests',

            // Gamification
            'view.trophies',
            'manage.trophies',
            'assign.trophies',

            // Analytics & Stats
            'view.user_stats',
            'view.course_stats',
            'analyze_weakness.user_stats',

            // Subscriptions & Payments
            'view.payments',
            'view.subscriptions',
            'create.subscriptions',
            'edit.subscriptions',
            'delete.subscriptions',
            'manage.subscriptions',
            'configure.pricing',
            'manage.receipts',
            'download.receipts',

            // Settings & Localization
            'access.settings',
            'manage.translations',
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
