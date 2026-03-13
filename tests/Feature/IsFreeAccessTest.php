<?php

namespace Tests\Feature;

use App\Models\BillingPlan;
use App\Models\Course;
use App\Models\Level;
use App\Models\Lesson;
use App\Models\User;
use App\Models\UserEntitlement;
use App\Models\UserFeature;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Spatie\Permission\Models\Role;

class IsFreeAccessTest extends TestCase
{
    use RefreshDatabase;

    protected $user;
    protected $course;
    protected $freeLevel;
    protected $paidLevel;
    protected $freeLesson;
    protected $paidLesson;

    protected function setUp(): void
    {
        parent::setUp();

        // Ensure role exists first
        Role::create(['name' => 'Student']);

        // Create User
        $this->user = User::factory()->create();

        // Create Course
        $this->course = Course::factory()->create(['status' => 'published']);

        // Create Free Level
        $this->freeLevel = Level::factory()->create([
            'course_id' => $this->course->id,
            'status' => 'published',
            'is_free' => true,
            'sort_order' => 1,
        ]);

        // Create Paid Level
        $this->paidLevel = Level::factory()->create([
            'course_id' => $this->course->id,
            'status' => 'published',
            'is_free' => false,
            'sort_order' => 2,
        ]);

        // Create Free Lesson in Free Level
        $this->freeLesson = Lesson::factory()->create([
            'level_id' => $this->freeLevel->id,
            'status' => 'published',
            'is_free' => true,
            'sort_order' => 1,
        ]);

        // Create Paid Lesson in Paid Level
        $this->paidLesson = Lesson::factory()->create([
            'level_id' => $this->paidLevel->id,
            'status' => 'published',
            'is_free' => false,
            'sort_order' => 1,
        ]);
    }

    public function test_user_without_features_cannot_access_anything()
    {
        // Set active course
        $this->user->active_course_id = $this->course->id;
        $this->user->save();

        // Check Course Content (Dashboard)
        $response = $this->actingAs($this->user)
            ->getJson("/api/learner/course-content");

        // Should be 403 because no entitlement at all
        $response->assertStatus(403);
    }

    public function test_user_with_free_access_can_access_free_level_but_not_paid()
    {
        // Grant Free Access Capability via Entitlement
        $plan = BillingPlan::create([
            'name' => 'Free Plan',
            'price' => 0,
            'currency' => 'USD',
            'access_type' => 'lifetime',
            'billing_type' => 'one_time',
            'is_active' => true
        ]);
        $plan->courses()->attach($this->course->id);

        $entitlement = UserEntitlement::create([
            'user_id' => $this->user->id,
            'billing_plan_id' => $plan->id,
            'status' => 'active',
            'starts_at' => now(),
        ]);

        UserFeature::create([
            'user_entitlement_id' => $entitlement->id,
            'scope_type' => 'App\Models\Course',
            'scope_id' => $this->course->id,
            'feature_code' => 'content.free.access',
            'value' => '1',
        ]);

        $this->user->active_course_id = $this->course->id;
        $this->user->save();

        // 1. Check Course Content Dashboard
        $response = $this->actingAs($this->user)
            ->getJson("/api/learner/course-content");

        $response->assertStatus(200);

        // Verify levels status in response
        $levels = $response->json('levels');
        $respFreeLevel = collect($levels)->firstWhere('id', $this->freeLevel->id);
        $respPaidLevel = collect($levels)->firstWhere('id', $this->paidLevel->id);

        // Free level should be unlocked (status unlocked injected)
        $this->assertEquals('unlocked', $respFreeLevel['currentUserProgress']['status']);

        // Paid level should be locked (or at least not explicitly unlocked by free logic)
        // Wait, sequential access might unlock it if free level is completed?
        // No, Level::isAccessibleToUser checks features.
        // If user doesn't have 'content.paid.access', isAccessibleToUser returns false.
        // And CoursesContentController uses isAccessibleToUser?
        // No, CoursesContentController uses it for Lesson access check, but for Dashboard level listing it uses logic:
        // "3. If Level is FREE and User has feature -> Unlock it."

        // For paid level, it falls through to standard logic (sequential).
        // If free level is completed, paid level *would* be unlocked via sequential logic...
        // BUT does sequential logic check for paid feature?
        // In `CoursesContentController`, the level loop determines "locked" status for items.
        // It doesn't seem to check `isAccessibleToUser` for the level itself to determine if it should be locked on the dashboard?
        // Wait, `isLevelUnlocked` logic in controller:
        // "If explicit status exists -> Use it."
        // "If Level is FREE and User has feature -> Unlock it."

        // If paid level is NOT free, and user has NO status, it checks if it's first level.
        // If it's 2nd level, it remains locked unless previous is completed.
        // If previous (free) level is completed, then `currentLevelPreviousItemCompleted` logic handles *items* within the level?
        // No, `isLevelUnlocked` determines if items are accessible.

        // Issue: The controller dashboard logic primarily handles *sequence*.
        // It doesn't seem to strictly enforce "Paid Feature" for the *listing* of levels on the dashboard.
        // However, `isAccessibleToUser` IS used in `showLesson` (actual content access).

        // So on Dashboard, the user might see the level as "unlocked" (if sequential), but when clicking a lesson, `showLesson` will block them?
        // Let's verify `showLesson` behavior.

        // 2. Access Free Lesson Content
        $response = $this->actingAs($this->user)
            ->getJson("/api/learner/lessons/{$this->freeLesson->id}/content");

        $response->assertStatus(200);

        // 3. Access Paid Lesson Content (in Paid Level)
        // Even if sequential access allows it on dashboard, strict check should fail.
        $response = $this->actingAs($this->user)
            ->getJson("/api/learner/lessons/{$this->paidLesson->id}/content");

        $response->assertStatus(403);
    }

    public function test_user_with_paid_access_can_access_paid_level()
    {
        // Grant Paid Access Capability
        $plan = BillingPlan::create([
            'name' => 'Paid Plan',
            'price' => 100,
            'currency' => 'USD',
            'access_type' => 'lifetime',
            'billing_type' => 'one_time',
            'is_active' => true
        ]);
        $plan->courses()->attach($this->course->id);

        $entitlement = UserEntitlement::create([
            'user_id' => $this->user->id,
            'billing_plan_id' => $plan->id,
            'status' => 'active',
            'starts_at' => now(),
        ]);

        UserFeature::create([
            'user_entitlement_id' => $entitlement->id,
            'scope_type' => 'App\Models\Course',
            'scope_id' => $this->course->id,
            'feature_code' => 'content.paid.access',
            'value' => '1',
        ]);

        $this->user->active_course_id = $this->course->id;
        $this->user->save();

        // Let's mark Level 1 as completed to satisfy sequence
        \App\Models\UserLevelProgress::create([
            'user_id' => $this->user->id,
            'course_id' => $this->course->id,
            'level_id' => $this->freeLevel->id,
            'status' => 'completed'
        ]);

        // And also unlock Level 2
        \App\Models\UserLevelProgress::create([
            'user_id' => $this->user->id,
            'course_id' => $this->course->id,
            'level_id' => $this->paidLevel->id,
            'status' => 'unlocked'
        ]);

        // Now try accessing paid lesson
        $response = $this->actingAs($this->user)
            ->getJson("/api/learner/lessons/{$this->paidLesson->id}/content");

        $response->assertStatus(200);
    }

    public function test_user_with_free_access_sees_paid_lesson_locked_on_dashboard()
    {
        // Setup: Free Plan only
        $plan = BillingPlan::create([
            'name' => 'Free Plan',
            'price' => 0,
            'currency' => 'USD',
            'access_type' => 'lifetime',
            'billing_type' => 'one_time',
            'is_active' => true
        ]);
        $plan->courses()->attach($this->course->id);

        $entitlement = UserEntitlement::create([
            'user_id' => $this->user->id,
            'billing_plan_id' => $plan->id,
            'status' => 'active',
            'starts_at' => now(),
        ]);

        UserFeature::create([
            'user_entitlement_id' => $entitlement->id,
            'scope_type' => 'App\Models\Course',
            'scope_id' => $this->course->id,
            'feature_code' => 'content.free.access',
            'value' => '1',
        ]);

        $this->user->active_course_id = $this->course->id;
        $this->user->save();

        // Even if we unlock the level (sequentially or explicitly), the paid lesson should be locked
        \App\Models\UserLevelProgress::create([
            'user_id' => $this->user->id,
            'course_id' => $this->course->id,
            'level_id' => $this->paidLevel->id,
            'status' => 'unlocked'
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/learner/course-content");

        $response->assertStatus(200);

        $levels = $response->json('levels');
        $respPaidLevel = collect($levels)->firstWhere('id', $this->paidLevel->id);

        // Check the lesson inside
        $lesson = $respPaidLevel['items'][0];
        $this->assertEquals($this->paidLesson->id, $lesson['id']);

        // Crucial check: Should be LOCKED because it is not free and user has no paid access
        $this->assertTrue($lesson['locked'], 'Paid lesson should be locked for free user even if level is unlocked');
    }
}
