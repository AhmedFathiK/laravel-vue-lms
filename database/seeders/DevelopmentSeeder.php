<?php

namespace Database\Seeders;

use App\Models\Course;
use App\Models\CourseCategory;
use App\Models\Lesson;
use App\Models\Level;
use App\Models\Question;
use App\Models\Slide;
use App\Models\Term;
use App\Models\User;
use Illuminate\Database\Seeder;

class DevelopmentSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('Seeding development data...');

        // Users
        $this->command->info('Creating Users...');
        User::factory()->count(10)->create();

        // Course Categories
        $this->command->info('Creating Courses...');
        $categories = CourseCategory::factory()->count(3)->create();

        foreach ($categories as $category) {
            // Courses
            $courses = Course::factory()->count(2)->for($category, 'category')->create();

            foreach ($courses as $course) {
                // Terms for this course
                $terms = Term::factory()->count(10)->for($course)->create();

                // Questions for this course
                $questions = Question::factory()->count(10)->for($course)->state(['type' => 'mcq'])->create();

                // Levels
                $levels = Level::factory()->count(3)->for($course)->create();

                foreach ($levels as $level) {
                    // Lessons
                    $lessons = Lesson::factory()->count(5)->for($level)->create();

                    foreach ($lessons as $lesson) {
                        // Slides
                        // 1. Content Slide
                        Slide::factory()->for($lesson)->create(['type' => 'explanation']);

                        // 2. MCQ Slide (pick one of the course questions)
                        if ($questions->isNotEmpty()) {
                            $q = $questions->random();
                            Slide::factory()->for($lesson)->create([
                                'type' => 'mcq',
                                'question_id' => $q->id,
                                'content' => json_encode([]),
                            ]);
                        }

                        // 3. Term Slide (pick one of the course terms)
                        if ($terms->isNotEmpty()) {
                            $t = $terms->random();
                            Slide::factory()->for($lesson)->create([
                                'type' => 'term',
                                'term_id' => $t->id,
                                'content' => json_encode([]),
                            ]);
                        }
                    }
                }
            }
        }

        $this->command->info('Development data seeded successfully!');
    }
}
