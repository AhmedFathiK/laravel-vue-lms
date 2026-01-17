<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\Lesson;
use App\Models\Level;
use App\Models\BillingPlan;
use App\Models\User;
use App\Models\UserStudiedLesson;
use App\Models\UserEntitlement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CourseAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::create(['name' => 'Student', 'guard_name' => 'web']);
    }

    public function test_user_cannot_access_lesson_if_not_enrolled()
    {
        $user = User::factory()->create();
        $course = Course::factory()->create(['status' => 'published']);
        $level = Level::factory()->create(['course_id' => $course->id, 'status' => 'published']);
        $lesson = Lesson::factory()->create(['level_id' => $level->id, 'status' => 'published']);

        $this->actingAs($user)
            ->getJson("/api/learner/lessons/{$lesson->id}/content")
            ->assertStatus(403)
            ->assertJson(['error' => 'You are not enrolled in this course.']);
    }

    public function test_user_can_access_lesson_if_enrolled_and_entitled()
    {
        $user = User::factory()->create();
        $course = Course::factory()->create(['status' => 'published']);
        $level = Level::factory()->create(['course_id' => $course->id, 'status' => 'published']);
        $lesson = Lesson::factory()->create(['level_id' => $level->id, 'status' => 'published']);
        $plan = BillingPlan::create([
            'name' => 'Test Plan',
            'price' => 100,
            'currency' => 'USD',
            'billing_type' => 'one_time',
            'access_type' => 'lifetime',
            'is_active' => true,
        ]);

        $entitlement = UserEntitlement::create([
            'user_id' => $user->id,
            'billing_plan_id' => $plan->id,
            'starts_at' => now(),
            'status' => 'active',
        ]);

        CourseEnrollment::create([
            'user_id' => $user->id,
            'course_id' => $course->id,
            'user_entitlement_id' => $entitlement->id,
        ]);

        $this->actingAs($user)
            ->getJson("/api/learner/lessons/{$lesson->id}/content")
            ->assertStatus(200);
    }

    public function test_user_cannot_access_lesson_if_entitlement_expired()
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
            'starts_at' => now()->subMonths(2),
            'ends_at' => now()->subDay(),
            'status' => 'expired',
        ]);

        CourseEnrollment::create([
            'user_id' => $user->id,
            'course_id' => $course->id,
            'user_entitlement_id' => $entitlement->id,
        ]);

        $this->actingAs($user)
            ->getJson("/api/learner/lessons/{$lesson->id}/content")
            ->assertStatus(403);
    }

    public function test_user_can_access_free_lesson_even_if_entitlement_expired()
    {
        $user = User::factory()->create();
        $course = Course::factory()->create(['status' => 'published']);
        $level = Level::factory()->create(['course_id' => $course->id, 'status' => 'published']);
        $lesson = Lesson::factory()->create(['level_id' => $level->id, 'status' => 'published', 'is_free' => true]);
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
            'starts_at' => now()->subMonths(2),
            'ends_at' => now()->subDay(),
            'status' => 'expired',
        ]);

        CourseEnrollment::create([
            'user_id' => $user->id,
            'course_id' => $course->id,
            'user_entitlement_id' => $entitlement->id,
        ]);

        $this->actingAs($user)
            ->getJson("/api/learner/lessons/{$lesson->id}/content")
            ->assertStatus(200);
    }

    public function test_user_cannot_access_second_lesson_if_first_not_completed()
    {
        $user = User::factory()->create();
        $course = Course::factory()->create(['status' => 'published']);
        $level = Level::factory()->create(['course_id' => $course->id, 'status' => 'published']);
        $lesson1 = Lesson::factory()->create(['level_id' => $level->id, 'status' => 'published', 'sort_order' => 1]);
        $lesson2 = Lesson::factory()->create(['level_id' => $level->id, 'status' => 'published', 'sort_order' => 2]);

        $plan = BillingPlan::create([
            'name' => 'Test Plan',
            'price' => 100,
            'currency' => 'USD',
            'billing_type' => 'one_time',
            'access_type' => 'lifetime',
            'is_active' => true,
        ]);

        $entitlement = UserEntitlement::create([
            'user_id' => $user->id,
            'billing_plan_id' => $plan->id,
            'starts_at' => now(),
            'status' => 'active',
        ]);

        CourseEnrollment::create([
            'user_id' => $user->id,
            'course_id' => $course->id,
            'user_entitlement_id' => $entitlement->id,
        ]);

        $this->actingAs($user)
            ->getJson("/api/learner/lessons/{$lesson2->id}/content")
            ->assertStatus(403)
            ->assertJson(['error' => 'You must complete the previous lesson first.']);
    }

    public function test_user_can_access_second_lesson_if_first_completed()
    {
        $user = User::factory()->create();
        $course = Course::factory()->create(['status' => 'published']);
        $level = Level::factory()->create(['course_id' => $course->id, 'status' => 'published']);
        $lesson1 = Lesson::factory()->create(['level_id' => $level->id, 'status' => 'published', 'sort_order' => 1]);
        $lesson2 = Lesson::factory()->create(['level_id' => $level->id, 'status' => 'published', 'sort_order' => 2]);

        $plan = BillingPlan::create([
            'name' => 'Test Plan',
            'price' => 100,
            'currency' => 'USD',
            'billing_type' => 'one_time',
            'access_type' => 'lifetime',
            'is_active' => true,
        ]);

        $entitlement = UserEntitlement::create([
            'user_id' => $user->id,
            'billing_plan_id' => $plan->id,
            'starts_at' => now(),
            'status' => 'active',
        ]);

        CourseEnrollment::create([
            'user_id' => $user->id,
            'course_id' => $course->id,
            'user_entitlement_id' => $entitlement->id,
        ]);
        
        // Mark lesson 1 as completed
        UserStudiedLesson::create([
            'user_id' => $user->id,
            'course_id' => $course->id,
            'level_id' => $level->id,
            'lesson_id' => $lesson1->id,
        ]);

        $this->actingAs($user)
            ->getJson("/api/learner/lessons/{$lesson2->id}/content")
            ->assertStatus(200);
    }
    
    public function test_user_can_access_free_second_lesson_even_if_first_not_completed()
    {
        $user = User::factory()->create();
        $course = Course::factory()->create(['status' => 'published']);
        $level = Level::factory()->create(['course_id' => $course->id, 'status' => 'published']);
        $lesson1 = Lesson::factory()->create(['level_id' => $level->id, 'status' => 'published', 'sort_order' => 1]);
        $lesson2 = Lesson::factory()->create(['level_id' => $level->id, 'status' => 'published', 'sort_order' => 2, 'is_free' => true]);

        // Free lessons should bypass sequence checks
        $this->actingAs($user)
            ->getJson("/api/learner/lessons/{$lesson2->id}/content")
            ->assertStatus(200);
    }
}
