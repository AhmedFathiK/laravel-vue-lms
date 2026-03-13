<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\Lesson;
use App\Models\Level;
use App\Models\BillingPlan;
use App\Models\User;
use App\Models\UserEntitlement;
use App\Models\UserCapability;
use App\Models\UserLevelProgress;
use Spatie\Permission\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LearnerLessonAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_learner_cannot_access_unpublished_lesson()
    {
        // Create role required by UserFactory
        Role::create(['name' => 'Student', 'guard_name' => 'web']);

        // Create user and course
        $user = User::factory()->create();
        $course = Course::factory()->create(['status' => 'published']);

        // Create free billing plan
        $plan = BillingPlan::create([
            'name' => 'Free Access',
            'price' => 0,
            'currency' => 'USD',
            'billing_type' => 'free',
            'access_type' => 'lifetime',
            'is_active' => true,
        ]);

        // Enroll user and entitle
        CourseEnrollment::create([
            'user_id' => $user->id,
            'course_id' => $course->id,
            'enrolled_at' => now(),
        ]);

        UserEntitlement::create([
            'user_id' => $user->id,
            'billing_plan_id' => $plan->id,
            'status' => 'active',
            'starts_at' => now(),
        ]);

        // Create published level
        $level = Level::factory()->create([
            'course_id' => $course->id,
            'status' => 'published',
        ]);

        // Create draft lesson
        $draftLesson = Lesson::factory()->create([
            'level_id' => $level->id,
            'status' => 'draft',
        ]);

        // Act
        $response = $this->actingAs($user)
            ->getJson("/api/learner/lessons/{$draftLesson->id}/content");

        // Assert
        $response->assertStatus(404);
    }

    public function test_learner_cannot_access_lesson_in_unpublished_level()
    {
        // Create role required by UserFactory
        $role = Role::where('name', 'Student')->first();
        if (!$role) {
            Role::create(['name' => 'Student', 'guard_name' => 'web']);
        }

        // Create user and course
        $user = User::factory()->create();
        $course = Course::factory()->create(['status' => 'published']);

        // Create free billing plan
        $plan = BillingPlan::create([
            'name' => 'Free Access',
            'price' => 0,
            'currency' => 'USD',
            'billing_type' => 'free',
            'access_type' => 'lifetime',
            'is_active' => true,
        ]);

        // Enroll user and entitle
        CourseEnrollment::create([
            'user_id' => $user->id,
            'course_id' => $course->id,
            'enrolled_at' => now(),
        ]);

        UserEntitlement::create([
            'user_id' => $user->id,
            'billing_plan_id' => $plan->id,
            'status' => 'active',
            'starts_at' => now(),
        ]);

        // Create draft level
        $draftLevel = Level::factory()->create([
            'course_id' => $course->id,
            'status' => 'draft',
        ]);

        // Create published lesson (but in draft level)
        $lesson = Lesson::factory()->create([
            'level_id' => $draftLevel->id,
            'status' => 'published',
        ]);

        // Act
        $response = $this->actingAs($user)
            ->getJson("/api/learner/lessons/{$lesson->id}/content");

        // Assert
        $response->assertStatus(404);
    }

    public function test_first_level_is_unlocked_for_new_user()
    {
        // 0. Create Role (if not exists)
        if (!Role::where('name', 'Student')->exists()) {
            Role::create(['name' => 'Student', 'guard_name' => 'web']);
        }

        // 1. Create User
        $user = User::factory()->create();

        // 2. Create Course and Levels
        $course = Course::factory()->create([
            'status' => 'published',
            'placement_exam_id' => null,
            'final_exam_id' => null,
        ]);

        $level1 = Level::factory()->create([
            'course_id' => $course->id,
            'status' => 'published',
            'sort_order' => 1,
        ]);

        $level2 = Level::factory()->create([
            'course_id' => $course->id,
            'status' => 'published',
            'sort_order' => 2,
        ]);

        // 3. Entitlement
        $plan = BillingPlan::create([
            'name' => 'Test Plan',
            'price' => 100,
            'currency' => 'USD',
            'billing_type' => 'one_time',
            'access_type' => 'lifetime',
            'is_active' => true
        ]);
        $plan->courses()->attach($course->id);

        $entitlement = UserEntitlement::create([
            'user_id' => $user->id,
            'billing_plan_id' => $plan->id,
            'status' => 'active',
            'starts_at' => now(),
        ]);

        UserCapability::create([
            'user_entitlement_id' => $entitlement->id,
            'scope_type' => 'App\Models\Course',
            'scope_id' => $course->id,
            'feature_code' => 'access_course',
            'value' => '1',
        ]);

        // 4. Request Course Content
        $user->active_course_id = $course->id;
        $user->save();

        $response = $this->actingAs($user)
            ->getJson("/api/learner/course-content");

        $response->assertStatus(200);

        $levels = $response->json('levels');
        $this->assertCount(2, $levels);

        $firstLevel = $levels[0];

        $this->assertEquals($level1->id, $firstLevel['id']);

        // Check for injected status with correct casing
        $this->assertArrayHasKey('currentUserProgress', $firstLevel, 'currentUserProgress key missing');

        $this->assertEquals(
            UserLevelProgress::STATUS_UNLOCKED,
            $firstLevel['currentUserProgress']['status'],
            "First level should have UNLOCKED status injected"
        );
    }
}
