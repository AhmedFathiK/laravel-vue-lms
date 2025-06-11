<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use App\Models\User;

class RolePermissionSeeder extends Seeder
{
    public function run()
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

            // Admin Panel
            'access.admin_panel',
        ];

        // Create permissions
        foreach ($permissions as $permission) {
            Permission::create(['name' => $permission]);
        }

        // Create roles and assign permissions

        // 1. Super Admin - Full access to everything
        $superAdmin = Role::create(['name' => 'super_admin', 'is_protected' => true]);
        $superAdmin->givePermissionTo(Permission::all());

        // 2. Admin - Most permissions but not super sensitive ones
        $admin = Role::create(['name' => 'admin']);
        $admin->givePermissionTo([
            // User Management (except delete and ban)
            'view.user',
            'create.user',
            'edit.user',
            'assign_role.user',

            // Role Management (view only)
            'view.role',

            // Full Course Management
            'view.course',
            'create.course',
            'edit.course',
            'delete.course',

            // Full Level Management
            'view.level',
            'create.level',
            'edit.level',
            'delete.level',
            'unlock.level',

            // Full Lesson Management
            'view.lesson',
            'create.lesson',
            'edit.lesson',
            'delete.lesson',
            'configure.lesson',
            'add_video.lesson',

            // Full Slide Management
            'view.slide',
            'create.slide',
            'edit.slide',
            'delete.slide',
            'reorder.slide',

            // Full Term Management
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

            // Analytics
            'view.user_stat',
            'view.course_stat',
            'analyze_weakness.user_stat',

            // Payments (view only)
            'view.payment',
            'download.receipt',

            // Settings
            'access.setting',
            'manage.translation',
            'manage.localization',

            // Admin Panel
            'access.admin_panel',
        ]);

        // 3. Content Manager - Focus on content creation and management
        $contentManager = Role::create(['name' => 'content_manager']);
        $contentManager->givePermissionTo([
            // Course Management
            'view.course',
            'create.course',
            'edit.course',

            // Level Management
            'view.level',
            'create.level',
            'edit.level',
            'unlock.level',

            // Lesson Management
            'view.lesson',
            'create.lesson',
            'edit.lesson',
            'configure.lesson',
            'add_video.lesson',

            // Slide Management
            'view.slide',
            'create.slide',
            'edit.slide',
            'delete.slide',
            'reorder.slide',

            // Term Management
            'view.term',
            'create.term',
            'edit.term',
            'translate.term',
            'link.term',
            'configure_revision.term',

            // Assessment System
            'view questions',
            'create questions',
            'edit questions',
            'view exams',
            'create exams',
            'edit exams',
            'view exam sections',
            'create exam sections',
            'edit exam sections',
            'grade exams',

            // Placement Tests
            'view.placement_test',
            'create.placement_test',
            'edit.placement_test',

            // Translation
            'manage.translation',
            'manage.localization',

            // Admin Panel Access
            'access.admin_panel',
        ]);

        // 4. Instructor/Teacher - Content creation with limited admin access
        $instructor = Role::create(['name' => 'instructor']);
        $instructor->givePermissionTo([
            // Course Management (limited)
            'view.course',
            'edit.course',

            // Level Management
            'view.level',
            'create.level',
            'edit.level',

            // Lesson Management
            'view.lesson',
            'create.lesson',
            'edit.lesson',
            'configure.lesson',
            'add_video.lesson',

            // Slide Management
            'view.slide',
            'create.slide',
            'edit.slide',
            'reorder.slide',

            // Term Management
            'view.term',
            'create.term',
            'edit.term',
            'translate.term',
            'link.term',

            // Assessment System
            'view questions',
            'create questions',
            'edit questions',
            'view exams',
            'create exams',
            'edit exams',
            'view exam sections',
            'create exam sections',
            'edit exam sections',
            'grade exams',

            // Placement Tests
            'view.placement_test',
            'create.placement_test',
            'edit.placement_test',
            'assign.placement_test',

            // Analytics (own content)
            'view.course_stat',

            // Admin Panel Access
            'access.admin_panel',
        ]);

        // 5. Analytics Manager - Focus on stats and data analysis
        $analyticsManager = Role::create(['name' => 'analytics_manager']);
        $analyticsManager->givePermissionTo([
            // User viewing for analysis
            'view.user',

            // Course viewing for analysis
            'view.course',

            // Full Analytics Access
            'view.user_stat',
            'view.course_stat',
            'analyze_weakness.user_stat',

            // Payment viewing for financial analysis
            'view.payment',
            'download.receipt',

            // Admin Panel Access
            'access.admin_panel',
        ]);

        // 6. Customer Support - User management and support
        $customerSupport = Role::create(['name' => 'customer_support']);
        $customerSupport->givePermissionTo([
            // User Management
            'view.user',
            'edit.user',
            'ban.user',

            // Course viewing to help users
            'view.course',
            'view.level',
            'view.lesson',

            // Trophy management for user motivation
            'view.trophy',
            'assign.trophy',

            // Subscription management
            'manage.subscription',
            'view.payment',
            'download.receipt',

            // User stats for support
            'view.user_stat',

            // Admin Panel Access
            'access.admin_panel',
        ]);

        // 7. Finance Manager - Payment and subscription focus
        $financeManager = Role::create(['name' => 'finance_manager']);
        $financeManager->givePermissionTo([
            // User viewing for financial context
            'view.user',

            // Payment Management
            'view.payment',
            'manage.subscription',
            'configure.pricing',
            'manage.receipt',
            'download.receipt',

            // User stats for financial analysis
            'view.user_stat',

            // Admin Panel Access
            'access.admin_panel',
        ]);

        // 8. Student - Basic user role with learning access
        $student = Role::create(['name' => 'student']);
        $student->givePermissionTo([
            // Basic viewing permissions
            'view.course',
            'view.level',
            'view.lesson',
            'view.slide',
            'view.term',
            'view.trophy',
            'download.receipt',
        ]);

        // Create a default super admin user if it doesn't exist
        $superAdminUser = User::where('email', 'superadmin@example.com')->first();
        if (!$superAdminUser) {
            $superAdminUser = User::create([
                'name' => 'Super Admin',
                'email' => 'superadmin@example.com',
                'password' => bcrypt('password'),
                'email_verified_at' => now(),
            ]);
        }
        $superAdminUser->assignRole('super_admin');

        // Create a default admin user if it doesn't exist
        $adminUser = User::where('email', 'admin@example.com')->first();
        if (!$adminUser) {
            $adminUser = User::create([
                'name' => 'Admin User',
                'email' => 'admin@example.com',
                'password' => bcrypt('password'),
                'email_verified_at' => now(),
            ]);
        }
        $adminUser->assignRole('admin');

        $this->command->info('Roles and permissions seeded successfully!');
        $this->command->info('Created roles: super_admin, admin, content_manager, instructor, analytics_manager, customer_support, finance_manager, student');
        $this->command->info('Super Admin: superadmin@example.com (password: password)');
        $this->command->info('Admin: admin@example.com (password: password)');
    }
}
