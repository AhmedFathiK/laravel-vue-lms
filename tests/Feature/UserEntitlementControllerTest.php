<?php

namespace Tests\Feature;

use App\Models\BillingPlan;
use App\Models\Course;
use App\Models\User;
use App\Models\UserEntitlement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class UserEntitlementControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_entitlement_response_includes_billing_plan_courses()
    {
        // Create permissions
        Permission::create(['name' => 'view.user_entitlements']);

        // Create roles
        $adminRole = Role::create(['name' => 'admin']);
        $adminRole->givePermissionTo('view.user_entitlements');

        Role::create(['name' => 'Student']);

        // Create an admin user
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        // Create a user
        $user = User::factory()->create();

        // Create a course
        $course = Course::factory()->create();

        // Create a billing plan attached to the course
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

        // Create an entitlement
        $entitlement = UserEntitlement::create([
            'user_id' => $user->id,
            'billing_plan_id' => $plan->id,
            'starts_at' => now(),
            'status' => 'active',
        ]);

        // Act: Make a request to the entitlement show endpoint
        $response = $this->actingAs($admin)->getJson("/api/admin/user-entitlements/{$entitlement->id}");

        // Assert: Check if the response contains the course data within billing_plan
        $response->assertStatus(200);

        $response->assertJsonStructure([
            'data' => [
                'id',
                'billingPlan' => [
                    'id',
                    'courses' => [
                        '*' => [
                            'id',
                            'title'
                        ]
                    ]
                ]
            ]
        ]);

        // Verify the course ID matches
        $this->assertEquals($course->id, $response->json('data.billingPlan.courses.0.id'));
    }
}
