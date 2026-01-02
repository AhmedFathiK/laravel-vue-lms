<?php

namespace Database\Factories;

use App\Models\Course;
use App\Models\Question;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Question>
 */
class QuestionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'course_id' => Course::factory(),
            'title' => $this->faker->sentence,
            'question_text' => $this->faker->paragraph,
            'type' => $this->faker->randomElement([
                Question::TYPE_MCQ,
                Question::TYPE_MATCHING,
                Question::TYPE_FILL_BLANK,
                Question::TYPE_REORDERING,
                Question::TYPE_FILL_BLANK_CHOICES,
                Question::TYPE_WRITING,
            ]),
            'points' => $this->faker->numberBetween(1, 10),
            'difficulty' => $this->faker->randomElement(['easy', 'medium', 'hard']),
            'correct_feedback' => $this->faker->optional()->sentence,
            'incorrect_feedback' => $this->faker->optional()->sentence,
            'tags' => $this->faker->words(3),
            'content' => [],
        ];
    }

    /**
     * Configure the model factory.
     */
    public function configure(): static
    {
        return $this->afterMaking(function (Question $question) {
            // Only generate data if content is empty
            if (!empty($question->content)) {
                return;
            }

            switch ($question->type) {
                case Question::TYPE_MCQ:
                    $options = [
                        $this->faker->word,
                        $this->faker->word,
                        $this->faker->word,
                        $this->faker->word,
                    ];

                    $randomIndex = $this->faker->numberBetween(0, 3);

                    $question->content = [
                        'options' => $options,
                        'correct_answer' => [(string)$randomIndex]
                    ];
                    break;

                case Question::TYPE_MATCHING:
                    $pairs = [];
                    for ($i = 0; $i < 4; $i++) {
                        $pairs[] = [
                            'left' => $this->faker->word,
                            'right' => $this->faker->word,
                        ];
                    }

                    $question->content = [
                        'pairs' => $pairs
                    ];
                    break;

                case Question::TYPE_FILL_BLANK:
                    // Needs [blank1], [blank2] in text
                    $text = "The " . $this->faker->word . " [blank1] over the [blank2] dog.";
                    $question->question_text = $text;

                    $question->content = [
                        'correct_answer' => [
                            ['jumps', 'leaped', 'hopped'], // Answers for blank1
                            ['lazy', 'sleeping'],          // Answers for blank2
                        ]
                    ];
                    break;

                case Question::TYPE_FILL_BLANK_CHOICES:
                    // Create blanks data structure
                    $blanks = [];
                    // Generate 2 blanks
                    for ($i = 0; $i < 2; $i++) {
                        $opts = [$this->faker->word, $this->faker->word, $this->faker->word];
                        // Pick a random index (0, 1, or 2) as the correct answer
                        $correctIndex = $this->faker->numberBetween(0, 2);

                        $blanks[] = [
                            'placeholder' => "Blank " . ($i + 1),
                            'options' => $opts,
                            'correct_answer' => (string)$correctIndex,
                        ];
                    }

                    $question->content = [
                        'blanks' => $blanks
                    ];
                    break;

                case Question::TYPE_REORDERING:
                    $items = [$this->faker->sentence, $this->faker->sentence, $this->faker->sentence, $this->faker->sentence];

                    $question->content = [
                        'items' => $items
                    ];
                    break;

                case Question::TYPE_WRITING:
                    $question->content = [
                        'grading_guidelines' => $this->faker->paragraph,
                        'min_words' => 50,
                        'max_words' => 100,
                    ];
                    break;
            }
        });
    }
}
