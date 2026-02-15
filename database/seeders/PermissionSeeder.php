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
            'edit.course_category',
            'delete.course_category',

            // Level Management
            'view.levels',
            'create.levels',
            'edit.levels',
            'delete.levels',
            'unlock.levels',
            'reorder.levels',

            // Lesson Management
            'view.lessons',
            'create.lessons',
            'edit.lessons',
            'delete.lessons',
            'add_video.lessons',
            'reorder.lessons',

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
            'create.trophies',
            'edit.trophies',
            'delete.trophies',

            // Analytics & Stats
            'view.user_stats',
            'view.course_stats',
            'analyze_weakness.user_stats',

            // Receipts & Payments
            'view.receipts',
            'create.receipts',
            'edit.receipts',
            'delete.receipts',
            'void.receipts',
            'store.receipts',
            'download.receipts',
            'resend.receipts',
            'view.payments',
            'create.payments',
            'edit.payments',
            'delete.payments',
            'store.payments',

            // Billing Plans
            'view.billing_plans',
            'create.billing_plans',
            'edit.billing_plans',
            'delete.billing_plans',
            'manage.billing_plans',

            // User Entitlements
            'view.user_entitlements',
            'create.user_entitlements',
            'edit.user_entitlements',
            'delete.user_entitlements',
            'manage.user_entitlements',

            // Settings & Localization
            'access.settings',
            'manage.translations',
            'manage.localization',

            // Trash Management
            'view.trash',
            'restore.trash',
            'delete.trash',

            // Expense Management
            'view.expenses',
            'create.expenses',
            'edit.expenses',
            'delete.expenses',
            'manage.expense_categories',

            // Financial Dashboard
            'view.financial_dashboard',

            // Admin Panel
            'access.admin_panel',
        ];

        // STEP 1: Get current permissions from DB
        $existingPermissions = Permission::pluck('name')->toArray();

        // STEP 2: Add missing permissions
        $toAdd = array_diff($permissions, $existingPermissions);
        foreach ($toAdd as $permissionName) {
            Permission::create(['name' => $permissionName]);
            $this->command->info("➕ Added: {$permissionName}");
        }

        // STEP 3: Delete removed permissions safely
        $toDelete = array_diff($existingPermissions, $permissions);
        foreach ($toDelete as $permissionName) {
            $permission = Permission::where('name', $permissionName)->first();

            if ($permission) {
                // Detach from roles and users first
                $permission->roles()->detach();
                $permission->users()->detach();
                $permission->delete();

                $this->command->warn("❌ Deleted: {$permissionName}");
            }
        }

        // STEP 4: Clear cache again after making changes
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $this->command->info("✅ Permissions synced successfully!");
    }
}
