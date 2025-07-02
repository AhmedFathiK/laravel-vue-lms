<?php

namespace Database\Factories;

use App\Models\Course;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Level>
 */
class LevelFactory extends Factory
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
            'title' => 'Level ' . $this->faker->unique()->numberBetween(1, 50),
            'description' => $this->faker->sentence(),
            'sort_order' => $this->faker->unique()->numberBetween(1, 50),
        ];
    }
}
