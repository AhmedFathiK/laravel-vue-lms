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
            'options' => [],
            'correct_answer' => [],
        ];
    }

    /**
     * Configure the model factory.
     */
    public function configure(): static
    {
        return $this->afterMaking(function (Question $question) {
            // Only generate data if options/correct_answer are empty
            // This prevents overwriting manual inputs if user provides them,
            // though standard Factory::create(['options' => ...]) merges AFTER this?
            // Actually, afterMaking runs after attributes are filled.

            if (!empty($question->options) || !empty($question->correct_answer)) {
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
                    $question->options = $options;

                    // Mimic Controller logic: correct_answer is an array of indices (integers)
                    // Pick 1 random index for simplicity, or multiple if needed
                    $randomIndex = $this->faker->numberBetween(0, 3);
                    $question->correct_answer = [$randomIndex];
                    break;

                case Question::TYPE_MATCHING:
                    $pairs = [];
                    for ($i = 0; $i < 4; $i++) {
                        $pairs[] = [
                            'left' => $this->faker->word,
                            'right' => $this->faker->word,
                        ];
                    }

                    // Mimic Controller logic: options stores the pairs
                    $question->options = $pairs;

                    // Mimic Controller logic: correct_answer stores the index mapping
                    $question->correct_answer = array_map(function ($index) {
                        return [
                            'left' => $index,
                            'right' => $index
                        ];
                    }, array_keys($pairs));
                    break;

                case Question::TYPE_FILL_BLANK:
                    // Needs [blank1], [blank2] in text
                    $text = "The " . $this->faker->word . " [blank1] over the [blank2] dog.";
                    $question->question_text = $text;
                    $question->correct_answer = [
                        ['jumps', 'leaped', 'hopped'], // Answers for blank1
                        ['lazy', 'sleeping'],          // Answers for blank2
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
                            'correct_answer' => $correctIndex, // Store index as integer/string based on payload
                        ];
                    }

                    // Mimic Controller logic: options stores the blanks structure
                    // In the payload, keys are 'placeholder', 'options', 'correctAnswer' (camelCase from frontend likely mapped)
                    // But controller expects:
                    // $data['options'] = $data['blanks'];
                    // $data['correct_answer'] = array_map(function ($blank) { return ['id' => ..., 'answer' => ...]; }, $data['blanks']);

                    // Wait, looking at the controller logic for FILL_BLANK_CHOICES:
                    // if (isset($data['blanks']) ...) {
                    //    $data['options'] = $data['blanks'];
                    //    $data['correct_answer'] = array_map(function ($blank) {
                    //        return [
                    //            'id' => $blank['id'] ?? uniqid(),
                    //            'answer' => $blank['correct_answer'] ?? null
                    //        ];
                    //    }, $data['blanks']);
                    // }

                    // So we need to construct $blanks exactly as the request would send it (or close to it)
                    // The payload shows: blanks[0][options][0], blanks[0][correctAnswer]

                    // Let's align with what the controller does.
                    // The controller takes 'blanks' array and splits it into 'options' and 'correct_answer'.

                    // So for the factory, we should directly set 'options' and 'correct_answer' 
                    // as they would appear IN THE DATABASE after the controller processes them.

                    $dbOptions = [];
                    $dbCorrectAnswer = [];

                    foreach ($blanks as $index => $blank) {
                        $id = (string)($index + 1); // Simple ID

                        // Database 'options' stores the structure (questions/choices)
                        // It seems the controller just saves the whole blank structure into options
                        // But the payload has 'correctAnswer' inside the blank.
                        // The controller extracts correct_answer separately.

                        // Let's assume the stored 'options' JSON looks like the 'blanks' array but maybe without the answer?
                        // " $data['options'] = $data['blanks']; " -> It saves everything including correctAnswer if it's in there.
                        // But usually correct answers are stripped from options for security if sent to frontend?
                        // However, for simplicity here, we follow the controller: it assigns $data['blanks'] to $data['options'].

                        // Re-constructing blank for DB options
                        $blankForDb = [
                            'id' => $id,
                            'placeholder' => $blank['placeholder'],
                            'options' => $blank['options'],
                            // Controller doesn't explicitly unset correct_answer from options, so it might persist there too
                        ];

                        $dbOptions[] = $blankForDb;

                        // Database 'correct_answer'
                        $dbCorrectAnswer[] = [
                            'id' => $id,
                            'answer' => $blank['correct_answer']
                        ];
                    }

                    $question->question_text = "The " . $this->faker->word . " [blank1] over the [blank2] dog.";
                    $question->options = $dbOptions;
                    $question->correct_answer = $dbCorrectAnswer;
                    break;

                case Question::TYPE_REORDERING:
                    $items = [$this->faker->sentence, $this->faker->sentence, $this->faker->sentence, $this->faker->sentence];

                    // Mimic Controller logic: options stores the items
                    $question->options = $items;

                    // Mimic Controller logic: correct_answer stores the correct order indices
                    $question->correct_answer = array_keys($items);
                    break;

                case Question::TYPE_WRITING:
                    // Mimic Controller logic: options stores guidelines and limits
                    // The payload sends these as separate fields: gradingGuidelines, minWords, maxWords
                    // The controller merges them into the 'options' JSON column.
                    $question->options = [
                        'grading_guidelines' => $this->faker->paragraph,
                        'min_words' => 50,
                        'max_words' => 100,
                    ];

                    // Writing questions typically don't have a single "correct_answer" in the traditional sense
                    $question->correct_answer = [];
                    break;
            }
        });
    }
}
