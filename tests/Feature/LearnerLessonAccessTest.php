<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\Lesson;
use App\Models\Level;
use App\Models\User;
use App\Models\SubscriptionPlan;
use App\Models\UserSubscription;
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

        // Create free subscription plan for the course
        $plan = SubscriptionPlan::create([
            'course_id' => $course->id,
            'name' => 'Free Access',
            'price' => 0,
            'currency' => 'USD',
            'billing_cycle' => 'one-time',
            'plan_type' => 'free',
            'is_free' => true,
            'is_active' => true,
        ]);

        // Enroll user and subscribe
        CourseEnrollment::create([
            'user_id' => $user->id,
            'course_id' => $course->id,
            'enrolled_at' => now(),
        ]);

        UserSubscription::create([
            'user_id' => $user->id,
            'subscription_plan_id' => $plan->id,
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

        // Create free subscription plan for the course
        $plan = SubscriptionPlan::create([
            'course_id' => $course->id,
            'name' => 'Free Access',
            'price' => 0,
            'currency' => 'USD',
            'billing_cycle' => 'one-time',
            'plan_type' => 'free',
            'is_free' => true,
            'is_active' => true,
        ]);

        // Enroll user and subscribe
        CourseEnrollment::create([
            'user_id' => $user->id,
            'course_id' => $course->id,
            'enrolled_at' => now(),
        ]);

        UserSubscription::create([
            'user_id' => $user->id,
            'subscription_plan_id' => $plan->id,
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
}
