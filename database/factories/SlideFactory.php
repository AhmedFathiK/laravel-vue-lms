<?php

namespace Database\Factories;

use App\Models\Lesson;
use App\Models\Question;
use App\Models\Term;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Slide>
 */
class SlideFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $type = $this->faker->randomElement(['explanation', 'mcq', 'term']);
        $content = ['en' => $this->faker->paragraph(3)];
        $questionId = null;
        $termId = null;

        if ($type === 'mcq') {
            $question = Question::factory()->create(['type' => 'mcq']);
            $questionId = $question->id;
            $content = []; // Content is stored in the question
        } elseif ($type === 'term') {
            $term = Term::factory()->create();
            $termId = $term->id;
            $content = []; // Content is stored in the term
        }

        return [
            'lesson_id' => Lesson::factory(),
            'type' => $type,
            'content' => json_encode($content),
            'sort_order' => $this->faker->numberBetween(1, 100),
            'question_id' => $questionId,
            'term_id' => $termId,
        ];
    }
}
