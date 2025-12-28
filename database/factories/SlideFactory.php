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
        return [
            'lesson_id' => Lesson::factory(),
            'type' => 'explanation',
            'content' => json_encode(['en' => $this->faker->paragraph(3)]),
            'sort_order' => $this->faker->numberBetween(1, 100),
            'question_id' => null,
            'term_id' => null,
        ];
    }

    public function mcq(): static
    {
        return $this->state(function (array $attributes) {
            $question = Question::factory()->create(['type' => 'mcq']);
            return [
                'type' => 'mcq',
                'question_id' => $question->id,
                'content' => json_encode([]),
            ];
        });
    }

    public function term(): static
    {
        return $this->state(function (array $attributes) {
            $term = Term::factory()->create();
            return [
                'type' => 'term',
                'term_id' => $term->id,
                'content' => json_encode([]),
            ];
        });
    }
}
