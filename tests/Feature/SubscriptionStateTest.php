<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\Lesson;
use App\Models\Level;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Models\UserSubscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SubscriptionStateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::create(['name' => 'Student', 'guard_name' => 'web']);
    }

    private function createSubscriptionWithStatus($status, $endsAt = null, $autoRenew = false)
    {
        $user = User::factory()->create();
        $course = Course::factory()->create(['status' => 'published']);
        $level = Level::factory()->create(['course_id' => $course->id, 'status' => 'published']);
        $lesson = Lesson::factory()->create(['level_id' => $level->id, 'status' => 'published', 'is_free' => false]);
        $plan = SubscriptionPlan::create([
            'course_id' => $course->id,
            'name' => 'Test Plan',
            'price' => 100,
            'currency' => 'USD',
            'plan_type' => 'recurring',
            'billing_cycle' => 'monthly',
            'is_active' => true,
        ]);

        $subscription = UserSubscription::create([
            'user_id' => $user->id,
            'subscription_plan_id' => $plan->id,
            'starts_at' => now()->subMonth(),
            'ends_at' => $endsAt ?? now()->addMonth(),
            'status' => $status,
            'auto_renew' => $autoRenew,
        ]);

        CourseEnrollment::create([
            'user_id' => $user->id,
            'course_id' => $course->id,
            'user_subscription_id' => $subscription->id,
        ]);

        return [$user, $lesson, $subscription];
    }

    public function test_user_can_access_content_if_subscription_past_due()
    {
        [$user, $lesson] = $this->createSubscriptionWithStatus(UserSubscription::STATUS_PAST_DUE);

        $this->actingAs($user)
            ->getJson("/api/learner/lessons/{$lesson->id}/content")
            ->assertStatus(200);
    }

    public function test_user_cannot_access_content_if_subscription_failed()
    {
        [$user, $lesson] = $this->createSubscriptionWithStatus(UserSubscription::STATUS_FAILED);

        $this->actingAs($user)
            ->getJson("/api/learner/lessons/{$lesson->id}/content")
            ->assertStatus(403);
    }

    public function test_user_cannot_access_content_if_subscription_expired()
    {
        [$user, $lesson] = $this->createSubscriptionWithStatus(UserSubscription::STATUS_EXPIRED);

        $this->actingAs($user)
            ->getJson("/api/learner/lessons/{$lesson->id}/content")
            ->assertStatus(403);
    }

    public function test_command_transitions_active_to_past_due()
    {
        [$user, $lesson, $subscription] = $this->createSubscriptionWithStatus(
            UserSubscription::STATUS_ACTIVE, 
            now()->subMinute(), 
            true // auto_renew
        );

        Artisan::call('subscriptions:update-status');

        $subscription->refresh();
        $this->assertEquals(UserSubscription::STATUS_PAST_DUE, $subscription->status);
    }

    public function test_command_transitions_active_to_expired()
    {
        [$user, $lesson, $subscription] = $this->createSubscriptionWithStatus(
            UserSubscription::STATUS_ACTIVE, 
            now()->subMinute(), 
            false // no auto_renew
        );

        Artisan::call('subscriptions:update-status');

        $subscription->refresh();
        $this->assertEquals(UserSubscription::STATUS_EXPIRED, $subscription->status);
    }

    public function test_command_transitions_past_due_to_failed()
    {
        [$user, $lesson, $subscription] = $this->createSubscriptionWithStatus(
            UserSubscription::STATUS_PAST_DUE, 
            now()->subDays(8), // > 7 days grace period
            true
        );

        Artisan::call('subscriptions:update-status');

        $subscription->refresh();
        $this->assertEquals(UserSubscription::STATUS_FAILED, $subscription->status);
    }

    public function test_command_does_not_fail_past_due_within_grace_period()
    {
        [$user, $lesson, $subscription] = $this->createSubscriptionWithStatus(
            UserSubscription::STATUS_PAST_DUE, 
            now()->subDays(6), // < 7 days grace period
            true
        );

        Artisan::call('subscriptions:update-status');

        $subscription->refresh();
        $this->assertEquals(UserSubscription::STATUS_PAST_DUE, $subscription->status);
    }
}
