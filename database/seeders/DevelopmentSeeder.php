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
use Illuminate\Database\Eloquent\Factories\Sequence;

class DevelopmentSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('Seeding development data...');

        // Users: 10 to 15 students
        $this->command->info('Creating Users...');
        $userCount = rand(10, 15);
        User::factory()->count($userCount)->create();

        // Ensure categories exist
        if (CourseCategory::count() === 0) {
            $this->call(CourseCategorySeeder::class);
        }
        $categories = CourseCategory::all();

        // Courses: 10 to 15
        $this->command->info('Creating Courses...');
        // Distribute 10-15 courses among categories
        $coursesToCreate = rand(10, 15);

        $courses = Course::factory()
            ->count($coursesToCreate)
            ->recycle($categories)
            ->create();

        foreach ($courses as $course) {
            // Terms and Questions pool for this course
            $terms = Term::factory()->count(20)->for($course)->create();
            $questions = Question::factory()->count(20)->for($course)->create();

            // Levels: 5 to 15 per course, named Level 1, Level 2, etc.
            $levelCount = rand(5, 15);

            $levels = Level::factory()
                ->count($levelCount)
                ->for($course)
                ->sequence(fn(Sequence $sequence) => [
                    'title' => 'Level ' . ($sequence->index + 1),
                    'sort_order' => $sequence->index + 1,
                ])
                ->create();

            foreach ($levels as $level) {
                // Lessons: 10 to 15 lessons per level
                $lessons = Lesson::factory()
                    ->count(rand(10, 15))
                    ->for($level)
                    ->create();

                foreach ($lessons as $lesson) {
                    // Slides: Exactly 10 slides per lesson
                    for ($i = 1; $i <= 10; $i++) {
                        $type = 'explanation';
                        $questionId = null;
                        $termId = null;
                        $content = json_encode(['en' => fake()->paragraph(3)]);

                        // Ensure we have at least one MCQ and one Term slide if possible
                        if ($i === 2 && $questions->isNotEmpty()) {
                            $q = $questions->random();
                            $type = $q->type;
                            $questionId = $q->id;
                            $content = json_encode([]);
                        } elseif ($i === 3 && $terms->isNotEmpty()) {
                            $type = 'term';
                            $t = $terms->random();
                            $termId = $t->id;
                            $content = json_encode([]);
                        } elseif ($i > 3) {
                            // Randomize the rest
                            $rand = rand(1, 10);
                            if ($rand <= 3 && $questions->isNotEmpty()) {
                                $q = $questions->random();
                                $type = $q->type;
                                $questionId = $q->id;
                                $content = json_encode([]);
                            } elseif ($rand <= 6 && $terms->isNotEmpty()) {
                                $type = 'term';
                                $t = $terms->random();
                                $termId = $t->id;
                                $content = json_encode([]);
                            }
                        }

                        Slide::factory()->for($lesson)->create([
                            'type' => $type,
                            'sort_order' => $i,
                            'question_id' => $questionId,
                            'term_id' => $termId,
                            'content' => $content,
                        ]);
                    }
                }
            }
        }

        $this->command->info('Development data seeded successfully!');
    }
}
