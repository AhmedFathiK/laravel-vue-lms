<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Lesson;
use App\Models\Level;
use App\Models\User;
use App\Models\UserStudiedLesson;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CascadingUserStudiedLessonDeleteTest extends TestCase
{
    use RefreshDatabase;

    public function test_deleting_level_cascades_to_user_studied_lessons()
    {
        // Seed permissions/roles
        $this->seed(\Database\Seeders\PermissionSeeder::class);
        \Spatie\Permission\Models\Role::create(['name' => 'Student']);

        // Setup data
        $user = User::factory()->create();
        $course = Course::factory()->create();
        $level = Level::factory()->create(['course_id' => $course->id]);
        $lesson = Lesson::factory()->create(['level_id' => $level->id]);

        $studiedLesson = UserStudiedLesson::create([
            'user_id' => $user->id,
            'course_id' => $course->id,
            'level_id' => $level->id,
            'lesson_id' => $lesson->id,
        ]);

        $this->assertDatabaseHas('user_studied_lessons', ['id' => $studiedLesson->id, 'deleted_at' => null]);

        // Delete level
        $level->delete();

        // Check if lesson is soft deleted
        $this->assertSoftDeleted('lessons', ['id' => $lesson->id]);

        // Check if user studied lesson is soft deleted
        $this->assertSoftDeleted('user_studied_lessons', ['id' => $studiedLesson->id]);

        // Restore level
        $level->restore();

        // Check if lesson is restored
        $this->assertDatabaseHas('lessons', ['id' => $lesson->id, 'deleted_at' => null]);

        // Check if user studied lesson is restored
        $this->assertDatabaseHas('user_studied_lessons', ['id' => $studiedLesson->id, 'deleted_at' => null]);
    }
}
