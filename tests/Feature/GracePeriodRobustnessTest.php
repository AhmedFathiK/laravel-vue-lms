<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\Lesson;
use App\Models\Level;
use App\Models\BillingPlan;
use App\Models\User;
use App\Models\UserEntitlement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class GracePeriodRobustnessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::create(['name' => 'Student', 'guard_name' => 'web']);
        
        // Ensure config is set for testing
        config(['entitlement.grace_period.percentage' => 10]);
        config(['entitlement.grace_period.max_days' => 7]);
    }

    private function createEntitlementContext($status, $startsAt, $endsAt)
    {
        $user = User::factory()->create();
        $course = Course::factory()->create(['status' => 'published']);
        $level = Level::factory()->create(['course_id' => $course->id, 'status' => 'published']);
        $lesson = Lesson::factory()->create(['level_id' => $level->id, 'status' => 'published', 'is_free' => false]);
        $plan = BillingPlan::create([
            'name' => 'Test Plan',
            'price' => 100,
            'currency' => 'USD',
            'billing_type' => 'recurring',
            'billing_interval' => 'month',
            'access_type' => 'while_active',
            'is_active' => true,
        ]);

        $entitlement = UserEntitlement::create([
            'user_id' => $user->id,
            'billing_plan_id' => $plan->id,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'status' => $status,
        ]);

        // Link course to plan via pivot table
        $plan->courses()->sync([$course->id]);

        CourseEnrollment::create([
            'user_id' => $user->id,
            'course_id' => $course->id,
            'user_entitlement_id' => $entitlement->id,
        ]);

        return [$user, $lesson, $entitlement];
    }

    /**
     * Test that a past_due entitlement loses access immediately when the calculated
     * grace period is exceeded, even if the scheduler has NOT run (status is still past_due).
     */
    public function test_past_due_loses_access_without_scheduler_when_grace_exceeded()
    {
        // Case: Grace period is max 7 days.
        // Entitlement ended 8 days ago.
        // Status is still 'past_due' (scheduler hasn't run to make it 'expired').
        
        [$user, $lesson, $entitlement] = $this->createEntitlementContext(
            UserEntitlement::STATUS_PAST_DUE,
            now()->subDays(38), // Started 38 days ago
            now()->subDays(8)   // Ended 8 days ago (Duration 30 days)
        );

        // Verify config assumptions
        // Duration = 30 days. Grace = 10% of 30 = 3 days.
        // Max grace = 7 days.
        // Effective Grace = 3 days.
        // We are 8 days past end. Access should be DENIED.

        // 1. Check isActive() directly
        $this->assertFalse($entitlement->isActive(), 'isActive() should be false dynamically');

        // 2. Check Level access logic (Single Source of Truth)
        $this->assertFalse($lesson->level->isAccessibleToUser($user), 'Level access should be denied');

        // 3. Check API access
        $this->actingAs($user)
            ->getJson("/api/learner/lessons/{$lesson->id}/content")
            ->assertStatus(403);
    }

    /**
     * Test that a past_due entitlement retains access if within the dynamic grace period.
     */
    public function test_past_due_retains_access_within_dynamic_grace_period()
    {
        // Case: Duration 30 days. Grace = 3 days.
        // Ended 1 day ago.
        
        [$user, $lesson, $entitlement] = $this->createEntitlementContext(
            UserEntitlement::STATUS_PAST_DUE,
            now()->subDays(31), // Started 31 days ago
            now()->subDays(1)   // Ended 1 day ago
        );

        // 1. Check isActive() directly
        $this->assertTrue($entitlement->isActive(), 'isActive() should be true within grace period');

        // 2. Check Level access logic
        $this->assertTrue($lesson->level->isAccessibleToUser($user), 'Level access should be granted');

        // 3. Check API access
        $this->actingAs($user)
            ->getJson("/api/learner/lessons/{$lesson->id}/content")
            ->assertStatus(200);
    }

    /**
     * Test that grace period calculation differs based on duration (Monthly vs Yearly).
     */
    public function test_grace_period_calculation_varies_by_duration()
    {
        // Scenario A: Short Duration (10 days)
        // Grace = 10% of 10 = 1 day.
        $userA = User::factory()->create();
        $entShort = UserEntitlement::create([
            'user_id' => $userA->id,
            'status' => UserEntitlement::STATUS_PAST_DUE,
            'starts_at' => now()->subDays(12),
            'ends_at' => now()->subDays(2), // Ended 2 days ago
        ]);
        // Grace is 1 day. Ended 2 days ago. Should be INACTIVE.
        $this->assertFalse($entShort->isActive(), 'Short duration should have short grace period');


        // Scenario B: Long Duration (100 days)
        // Grace = 10% of 100 = 10 days -> Capped at Max (7 days).
        $userB = User::factory()->create();
        $entLong = UserEntitlement::create([
            'user_id' => $userB->id,
            'status' => UserEntitlement::STATUS_PAST_DUE,
            'starts_at' => now()->subDays(105),
            'ends_at' => now()->subDays(5), // Ended 5 days ago
        ]);
        // Grace is 7 days. Ended 5 days ago. Should be ACTIVE.
        $this->assertTrue($entLong->isActive(), 'Long duration should have max grace period');

        // Scenario C: Long Duration Expired (8 days ago)
        $entLongExpired = UserEntitlement::create([
            'user_id' => $userB->id,
            'status' => UserEntitlement::STATUS_PAST_DUE,
            'starts_at' => now()->subDays(108),
            'ends_at' => now()->subDays(8), // Ended 8 days ago
        ]);
        // Grace is 7 days. Ended 8 days ago. Should be INACTIVE.
        $this->assertFalse($entLongExpired->isActive(), 'Long duration should expire after max grace period');
    }
}