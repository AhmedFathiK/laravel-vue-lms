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
use Illuminate\Support\Facades\Artisan;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EntitlementStateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::create(['name' => 'Student', 'guard_name' => 'web']);
    }

    private function createEntitlementWithStatus($status, $endsAt = null, $autoRenew = false)
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
            'starts_at' => now()->subMonth(),
            'ends_at' => $endsAt ?? now()->addMonth(),
            'status' => $status,
        ]);

        CourseEnrollment::create([
            'user_id' => $user->id,
            'course_id' => $course->id,
            'user_entitlement_id' => $entitlement->id,
        ]);

        return [$user, $lesson, $entitlement];
    }

    public function test_user_can_access_content_if_entitlement_past_due()
    {
        [$user, $lesson] = $this->createEntitlementWithStatus(UserEntitlement::STATUS_PAST_DUE);

        $this->actingAs($user)
            ->getJson("/api/learner/lessons/{$lesson->id}/content")
            ->assertStatus(200);
    }

    public function test_user_cannot_access_content_if_entitlement_revoked()
    {
        [$user, $lesson] = $this->createEntitlementWithStatus(UserEntitlement::STATUS_REVOKED);

        $this->actingAs($user)
            ->getJson("/api/learner/lessons/{$lesson->id}/content")
            ->assertStatus(403);
    }

    public function test_user_cannot_access_content_if_entitlement_expired()
    {
        [$user, $lesson] = $this->createEntitlementWithStatus(UserEntitlement::STATUS_EXPIRED);

        $this->actingAs($user)
            ->getJson("/api/learner/lessons/{$lesson->id}/content")
            ->assertStatus(403);
    }

    public function test_command_transitions_active_to_past_due()
    {
        [$user, $lesson, $entitlement] = $this->createEntitlementWithStatus(
            UserEntitlement::STATUS_ACTIVE, 
            now()->subMinute(), 
            true // auto_renew
        );

        Artisan::call('entitlements:update-status');

        $entitlement->refresh();
        $this->assertEquals(UserEntitlement::STATUS_PAST_DUE, $entitlement->status);
    }

    public function test_command_transitions_active_to_expired()
    {
        [$user, $lesson, $entitlement] = $this->createEntitlementWithStatus(
            UserEntitlement::STATUS_ACTIVE, 
            now()->subMinute(), 
            false // no auto_renew
        );

        Artisan::call('entitlements:update-status');

        $entitlement->refresh();
        $this->assertEquals(UserEntitlement::STATUS_EXPIRED, $entitlement->status);
    }

    public function test_command_transitions_past_due_to_failed()
    {
        [$user, $lesson, $entitlement] = $this->createEntitlementWithStatus(
            UserEntitlement::STATUS_PAST_DUE, 
            now()->subDays(8), // > 7 days grace period
            true
        );

        Artisan::call('entitlements:update-status');

        $entitlement->refresh();
        $this->assertEquals(UserEntitlement::STATUS_FAILED, $entitlement->status);
    }

    public function test_command_does_not_fail_past_due_within_grace_period()
    {
        [$user, $lesson, $entitlement] = $this->createEntitlementWithStatus(
            UserEntitlement::STATUS_PAST_DUE, 
            now()->subDays(6), // < 7 days grace period
            true
        );

        Artisan::call('entitlements:update-status');

        $entitlement->refresh();
        $this->assertEquals(UserEntitlement::STATUS_PAST_DUE, $entitlement->status);
    }
}
