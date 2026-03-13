<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\CourseCategory;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\Level;
use App\Models\User;
use App\Models\UserLevelProgress;
use App\Models\BillingPlan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;
use Laravel\Sanctum\Sanctum;

class PlacementExamResultTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        if (!Role::where('name', 'Student')->exists()) {
            Role::create(['name' => 'Student', 'guard_name' => 'web']);
        }
    }

    public function test_placement_exam_result_is_included_in_course_content_response()
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

        // Create Placement Exam
        $exam = Exam::create([
            'course_id' => $course->id,
            'title' => ['en' => 'Placement Exam'],
            'status' => 'published',
            'is_active' => true,
            'max_attempts' => 1,
            'time_limit' => 30,
            'passing_percentage' => 50,
            'randomize_questions' => false,
            'show_answers' => true,
        ]);

        $course->placement_exam_id = $exam->id;
        $course->save();

        // Create Level 1 & 2
        $level1 = Level::create([
            'course_id' => $course->id,
            'title' => ['en' => 'Level 1'],
            'sort_order' => 1,
            'status' => 'published'
        ]);

        $level2 = Level::create([
            'course_id' => $course->id,
            'title' => ['en' => 'Level 2'],
            'sort_order' => 2,
            'status' => 'published'
        ]);

        // Entitlement
        $plan = BillingPlan::create([
            'name' => 'Free Plan',
            'slug' => 'free-plan',
            'price' => 0,
            'is_active' => true,
            'billing_type' => 'one_time',
            'currency' => 'USD',
            'access_type' => 'lifetime',
        ]);
        $plan->courses()->attach($course->id);

        $entitlement = $user->entitlements()->create([
            'billing_plan_id' => $plan->id,
            'starts_at' => now(),
            'expires_at' => now()->addYear(),
            'status' => 'active',
        ]);

        $entitlement->features()->create([
            'feature_code' => 'course.access',
            'value' => 'true',
            'scope_type' => Course::class,
            'scope_id' => $course->id,
        ]);

        // 1. Visit Course - Placement Exam should be incomplete
        // Set active course first
        $res = $this->postJson('/api/user/active-course', ['course_id' => $course->id]);
        if ($res->status() !== 200) {
            dump($res->json());
        }
        $res->assertStatus(200);
        
        $response = $this->getJson("/api/learner/course-content");
        if ($response->status() !== 200) {
            dump($response->json());
        }
        $response->assertStatus(200);
        $data = $response->json();

        $this->assertFalse($data['placementExam']['completed']);
        $this->assertArrayNotHasKey('outcome', $data['placementExam']);

        // 2. Complete Placement Exam (Simulate Attempt)
        $attempt = ExamAttempt::create([
            'user_id' => $user->id,
            'exam_id' => $exam->id,
            'start_time' => now()->subMinutes(10),
            'end_time' => now(),
            'score' => 80,
            'max_score' => 100,
            'percentage' => 80,
            'status' => ExamAttempt::STATUS_COMPLETED,
            'is_passed' => true,
            'attempt_number' => 1,
            'placement_outcome_level_id' => $level2->id // Placed in Level 2
        ]);

        // 3. Visit Course again - Placement Exam should be completed with outcome
        // Ensure active course is still set (session persistence or token)
        $this->postJson('/api/user/active-course', ['course_id' => $course->id]);
        
        $response = $this->getJson("/api/learner/course-content");
        $response->assertStatus(200);
        $data = $response->json();

        $this->assertTrue($data['placementExam']['completed']);
        $this->assertArrayHasKey('outcome', $data['placementExam']);
        // Middleware converts keys to camelCase
        $this->assertEquals($level2->id, $data['placementExam']['outcome']['levelId']);
        $this->assertEquals(80, $data['placementExam']['outcome']['percentage']);
    }
}
