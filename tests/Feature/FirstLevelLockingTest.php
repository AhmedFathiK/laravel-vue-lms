<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\CourseCategory;
use App\Models\Level;
use App\Models\User;
use App\Models\UserLevelProgress;
use App\Models\BillingPlan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;
use Laravel\Sanctum\Sanctum;

class FirstLevelLockingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        if (!Role::where('name', 'Student')->exists()) {
            Role::create(['name' => 'Student', 'guard_name' => 'web']);
        }
    }

    public function test_first_level_unlock_is_persisted_to_prevent_locking_when_new_level_added()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $category = CourseCategory::create([
            'name' => ['en' => 'Category 1'],
            'slug' => 'category-1',
            'is_active' => true,
            'sort_order' => 0
        ]);

        $course = Course::create([
            'title' => ['en' => 'Test Course'],
            'status' => 'published',
            'course_category_id' => $category->id,
            'main_locale' => 'en'
        ]);

        // Create Level A (Initially First)
        $levelA = Level::create([
            'course_id' => $course->id,
            'title' => ['en' => 'Level A'],
            'sort_order' => 10,
            'status' => 'published'
        ]);

        // Mock Entitlement (User must have access to course)
        // Usually done via billing plan or capability
        // For simplicity, we can mock the entitlement check or just give the user the capability.
        // But the controller checks entitlements.
        
        // Let's create a free plan and enroll the user? 
        // Or just mock the EntitlementService? 
        // The controller uses $user->entitlements()->active()->...
        
        // Easier: Give user a role that has permission? No, it checks specific entitlement.
        // Let's create a fake entitlement.
        
        $plan = BillingPlan::create([
            'name' => 'Free Plan',
            'slug' => 'free-plan',
            'price' => 0,
            'is_active' => true,
            'billing_type' => 'one_time', // Required field
            'currency' => 'USD',
            'access_type' => 'lifetime',
        ]);
        $plan->courses()->attach($course->id);
        
        $entitlement = $user->entitlements()->create([
            'billing_plan_id' => $plan->id,
            'starts_at' => now(),
            'expires_at' => now()->addYear(),
            'status' => 'active', // Ensure status is active
        ]);
        
        // Grant capability for the course
        $entitlement->capabilities()->create([
            'user_id' => $user->id,
            'scope_type' => Course::class,
            'scope_id' => $course->id,
            'feature_code' => 'course.access', // Required
            'parameters' => [],
        ]);

        // 1. User visits course
        $response = $this->getJson("/api/learner/my-courses/{$course->id}");
        $response->assertStatus(200);

        // Verify Level A is unlocked in response
        $data = $response->json();
        $levelAData = collect($data['levels'])->firstWhere('id', $levelA->id);
        $this->assertEquals('unlocked', $levelAData['currentUserProgress']['status']);

        // 2. CRITICAL CHECK: Verify UserLevelProgress was created in DB
        // Without the fix, this assertion would FAIL
        $this->assertDatabaseHas('user_level_progress', [
            'user_id' => $user->id,
            'course_id' => $course->id,
            'level_id' => $levelA->id,
            'status' => UserLevelProgress::STATUS_UNLOCKED
        ]);

        // 3. Add Level B (Before Level A)
        $levelB = Level::create([
            'course_id' => $course->id,
            'title' => ['en' => 'Level B'],
            'sort_order' => 5, // Lower than 10
            'status' => 'published'
        ]);

        // 4. User visits course again
        $response2 = $this->getJson("/api/learner/my-courses/{$course->id}");
        $response2->assertStatus(200);
        $data2 = $response2->json();

        // Level B should be unlocked (it's the new first level)
        $levelBData = collect($data2['levels'])->firstWhere('id', $levelB->id);
        $this->assertEquals('unlocked', $levelBData['currentUserProgress']['status']);

        // Level A should STILL be unlocked (because we persisted the progress)
        $levelAData2 = collect($data2['levels'])->firstWhere('id', $levelA->id);
        $this->assertEquals('unlocked', $levelAData2['currentUserProgress']['status']);
    }
}
