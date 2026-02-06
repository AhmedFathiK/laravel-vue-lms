<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\CourseCategory;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\Lesson;
use App\Models\Level;
use App\Models\User;
use App\Models\UserLevelProgress;
use App\Models\UserStudiedLesson;
use App\Models\UserCapability;
use App\Models\UserEntitlement;
use App\Models\BillingPlan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class LevelStatusAndLockingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        if (!Role::where('name', 'Student')->exists()) {
            Role::create(['name' => 'Student', 'guard_name' => 'web']);
        }
    }

    public function test_level_marked_as_completed_when_all_lessons_studied()
    {
        $user = User::create([
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
        ]);

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

        $level = Level::create([
            'course_id' => $course->id,
            'title' => ['en' => 'Level 1'],
            'sort_order' => 1,
            'status' => 'published'
        ]);

        $lesson = Lesson::create([
            'level_id' => $level->id,
            'title' => ['en' => 'Lesson 1'],
            'status' => 'published'
        ]);

        $exam = Exam::create([
            'title' => ['en' => 'Level Exam'],
            'course_id' => $course->id,
            'passing_percentage' => 70,
            'status' => 'published',
            'placement_rules' => [],
        ]);
        $level->final_exam_id = $exam->id;
        $level->save();

        UserStudiedLesson::create([
            'user_id' => $user->id,
            'course_id' => $course->id,
            'level_id' => $level->id,
            'lesson_id' => $lesson->id,
        ]);

        $attempt = ExamAttempt::create([
            'user_id' => $user->id,
            'exam_id' => $exam->id,
            'start_time' => now(),
            'percentage' => 100,
            'is_passed' => true,
            'status' => ExamAttempt::STATUS_GRADED,
        ]);

        // Trigger the logic
        $method = new \ReflectionMethod(ExamAttempt::class, 'unlockNextLevel');
        $method->invoke($attempt);

        $progress = UserLevelProgress::where('user_id', $user->id)
            ->where('level_id', $level->id)
            ->first();

        $this->assertNotNull($progress);
        $this->assertEquals(UserLevelProgress::STATUS_COMPLETED, $progress->status);
    }

    public function test_level_marked_as_skipped_when_lessons_not_all_studied()
    {
        $user = User::create([
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => 'test2@example.com',
            'password' => bcrypt('password'),
        ]);

        $category = CourseCategory::create([
            'name' => ['en' => 'Category 1'],
            'slug' => 'category-2',
            'is_active' => true,
            'sort_order' => 0
        ]);

        $course = Course::create([
            'title' => ['en' => 'Test Course'],
            'status' => 'published',
            'course_category_id' => $category->id,
            'main_locale' => 'en'
        ]);

        $level = Level::create([
            'course_id' => $course->id,
            'title' => ['en' => 'Level 1'],
            'sort_order' => 1,
            'status' => 'published'
        ]);

        Lesson::create([
            'level_id' => $level->id,
            'title' => ['en' => 'Lesson 1'],
            'status' => 'published'
        ]);

        $exam = Exam::create([
            'title' => ['en' => 'Level Exam'],
            'course_id' => $course->id,
            'passing_percentage' => 70,
            'status' => 'published',
            'placement_rules' => [],
        ]);
        $level->final_exam_id = $exam->id;
        $level->save();

        // User did NOT study the lesson

        $attempt = ExamAttempt::create([
            'user_id' => $user->id,
            'exam_id' => $exam->id,
            'start_time' => now(),
            'percentage' => 100,
            'is_passed' => true,
            'status' => ExamAttempt::STATUS_GRADED,
        ]);

        // Trigger the logic
        $method = new \ReflectionMethod(ExamAttempt::class, 'unlockNextLevel');
        $method->invoke($attempt);

        $progress = UserLevelProgress::where('user_id', $user->id)
            ->where('level_id', $level->id)
            ->first();

        $this->assertNotNull($progress);
        $this->assertEquals(UserLevelProgress::STATUS_SKIPPED, $progress->status);
    }

    public function test_next_level_remains_unlocked_when_new_lesson_added_to_passed_level()
    {
        $user = User::create([
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => 'test3@example.com',
            'password' => bcrypt('password'),
        ]);

        $category = CourseCategory::create([
            'name' => ['en' => 'Category 1'],
            'slug' => 'category-3',
            'is_active' => true,
            'sort_order' => 0
        ]);

        $course = Course::create([
            'title' => ['en' => 'Test Course'],
            'status' => 'published',
            'course_category_id' => $category->id,
            'main_locale' => 'en'
        ]);

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

        $exam1 = Exam::create([
            'title' => ['en' => 'Level 1 Exam'],
            'course_id' => $course->id,
            'passing_percentage' => 70,
            'status' => 'published',
            'placement_rules' => [],
        ]);
        $level1->final_exam_id = $exam1->id;
        $level1->save();

        // Initially Level 1 passed, Level 2 unlocked
        UserLevelProgress::create([
            'user_id' => $user->id,
            'course_id' => $course->id,
            'level_id' => $level1->id,
            'status' => UserLevelProgress::STATUS_COMPLETED
        ]);

        UserLevelProgress::create([
            'user_id' => $user->id,
            'course_id' => $course->id,
            'level_id' => $level2->id,
            'status' => UserLevelProgress::STATUS_UNLOCKED
        ]);

        // Now simulate taking Level 1 exam again (e.g. retake) or some logic that calls unlockNextLevel
        $attempt = ExamAttempt::create([
            'user_id' => $user->id,
            'exam_id' => $exam1->id,
            'start_time' => now(),
            'percentage' => 100,
            'is_passed' => true,
            'status' => ExamAttempt::STATUS_GRADED,
        ]);

        // Add a NEW lesson to level 1 that wasn't studied
        Lesson::create([
            'level_id' => $level1->id,
            'title' => ['en' => 'New Unstudied Lesson'],
            'status' => 'published'
        ]);

        // Trigger the logic
        $method = new \ReflectionMethod(ExamAttempt::class, 'unlockNextLevel');
        $method->invoke($attempt);

        // Level 2 should STILL be UNLOCKED
        $progress2 = UserLevelProgress::where('user_id', $user->id)
            ->where('level_id', $level2->id)
            ->first();

        $this->assertEquals(UserLevelProgress::STATUS_UNLOCKED, $progress2->status);
    }
}
