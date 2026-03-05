<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Models\UserEntitlement;
use App\Models\BillingPlan;
use App\Models\Course;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class UserEntitlementGracePeriodTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_filter_entitlements_by_grace_period()
    {
        // Setup permissions
        // Check if permission exists or create it
        if (!Permission::where('name', 'view.user_entitlements')->exists()) {
            Permission::create(['name' => 'view.user_entitlements', 'guard_name' => 'web']);
        }

        if (!Role::where('name', 'admin')->exists()) {
            $role = Role::create(['name' => 'admin', 'guard_name' => 'web']);
        } else {
            $role = Role::findByName('admin', 'web');
        }

        if (!Role::where('name', 'Student')->exists()) {
            Role::create(['name' => 'Student', 'guard_name' => 'web']);
        }

        $role->givePermissionTo('view.user_entitlements');

        $admin = User::factory()->create();
        $admin->assignRole('admin');

        // Create a billing plan
        $course = Course::factory()->create();
        $plan = BillingPlan::create([
            'name' => 'Test Plan',
            'description' => 'Test Description',
            'price' => 100,
            'currency' => 'USD',
            'billing_type' => 'one_time',
            'billing_interval' => 'month',
            'access_type' => 'lifetime',
            'access_duration_days' => 30,
            'is_active' => true,
        ]);
        $plan->courses()->attach($course);

        // 1. Entitlement in Grace Period
        // Duration: 30 days (starts 31 days ago, ends 1 day ago)
        // Grace: 10% of 30 = 3 days.
        // Ends 1 day ago -> within 3 days grace.
        $graceEntitlement = UserEntitlement::create([
            'user_id' => User::factory()->create()->id,
            'billing_plan_id' => $plan->id,
            'status' => 'active', // Important: status is active in DB
            'starts_at' => now()->subDays(31),
            'ends_at' => now()->subDays(1), // Ends yesterday
            'auto_renew' => false,
        ]);

        // 2. Active Entitlement (Not Grace)
        $activeEntitlement = UserEntitlement::create([
            'user_id' => User::factory()->create()->id,
            'billing_plan_id' => $plan->id,
            'status' => 'active',
            'starts_at' => now()->subDays(10),
            'ends_at' => now()->addDays(20),
            'auto_renew' => false,
        ]);

        // 3. Expired Entitlement (Past Grace)
        // Ends 10 days ago (max grace is 7 days usually)
        $expiredEntitlement = UserEntitlement::create([
            'user_id' => User::factory()->create()->id,
            'billing_plan_id' => $plan->id,
            'status' => 'expired',
            'starts_at' => now()->subDays(40),
            'ends_at' => now()->subDays(20),
            'auto_renew' => false,
        ]);

        // Also create an 'active' status one that is past grace (simulating scheduler hasn't run yet)
        $staleEntitlement = UserEntitlement::create([
            'user_id' => User::factory()->create()->id,
            'billing_plan_id' => $plan->id,
            'status' => 'active',
            'starts_at' => now()->subDays(40),
            'ends_at' => now()->subDays(10), // 10 days ago > 7 days max grace
            'auto_renew' => false,
        ]);

        // Call API with status=grace_period
        $response = $this->actingAs($admin)->getJson('/api/admin/user-entitlements?status=grace_period');

        if ($response->status() === 404) {
            $response = $this->actingAs($admin)->getJson('/admin/user-entitlements?status=grace_period');
        }

        $response->assertStatus(200);

        $data = $response->json('data');

        $ids = collect($data)->pluck('id')->toArray();
        $this->assertContains($graceEntitlement->id, $ids);
        $this->assertNotContains($activeEntitlement->id, $ids);
        $this->assertNotContains($expiredEntitlement->id, $ids);
        $this->assertNotContains($staleEntitlement->id, $ids);

        // Verify is_grace_period flag
        $graceItem = collect($data)->firstWhere('id', $graceEntitlement->id);
        // The resource returns camelCase if middleware is active, but test might see snake_case if middleware isn't applied in test env the same way?
        // Actually, resource toArray returns snake_case keys usually.
        // Middleware converts keys.
        // Test response->json() will have keys as returned by controller.
        // If controller returns Resource::collection, it depends on resource toArray.
        // Resource toArray has 'is_grace_period'.
        // Middleware usually runs on response.
        // Let's check 'is_grace_period' first.
        $this->assertTrue($graceItem['is_grace_period'] ?? $graceItem['isGracePeriod'] ?? false);
    }

    public function test_entitlement_status_updates_to_expired_when_grace_period_ends()
    {
        // Setup permissions
        if (!Permission::where('name', 'view.user_entitlements')->exists()) {
            Permission::create(['name' => 'view.user_entitlements', 'guard_name' => 'web']);
        }
        if (!Role::where('name', 'admin')->exists()) {
            $role = Role::create(['name' => 'admin', 'guard_name' => 'web']);
        } else {
            $role = Role::findByName('admin', 'web');
        }
        $role->givePermissionTo('view.user_entitlements');

        if (!Role::where('name', 'Student')->exists()) {
            Role::create(['name' => 'Student', 'guard_name' => 'web']);
        }

        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $plan = BillingPlan::create([
            'name' => 'Test Plan',
            'price' => 100,
            'currency' => 'USD',
            'billing_type' => 'one_time',
            'billing_interval' => 'month',
            'access_type' => 'limited',
            'access_duration_days' => 30,
            'is_active' => true,
        ]);

        // Create a stale entitlement (active status but past grace period)
        $staleEntitlement = UserEntitlement::create([
            'user_id' => User::factory()->create()->id,
            'billing_plan_id' => $plan->id,
            'status' => 'active',
            'starts_at' => now()->subDays(60),
            'ends_at' => now()->subDays(20), // 20 days ago > 7 days max grace
            'auto_renew' => false,
        ]);

        // Accessing the entitlement (via API or calling isActive directly) should trigger update
        // Let's call the API to list entitlements, which triggers loading/resource transformation
        // The Resource calls isActive()

        $response = $this->actingAs($admin)->getJson('/api/admin/user-entitlements?status=expired');
        if ($response->status() === 404) {
            $response = $this->actingAs($admin)->getJson('/admin/user-entitlements?status=expired');
        }

        $response->assertStatus(200);

        // Direct check
        $staleEntitlement->refresh();

        // Manually call isActive to trigger the logic (it might not be called by refresh())
        $staleEntitlement->isActive();

        $this->assertEquals(UserEntitlement::STATUS_EXPIRED, $staleEntitlement->status);
    }
}
