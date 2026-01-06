<?php

namespace Database\Factories;

use App\Models\Concept;
use App\Models\Course;
use Illuminate\Database\Eloquent\Factories\Factory;

class ConceptFactory extends Factory
{
    protected $model = Concept::class;

    public function definition(): array
    {
        return [
            'course_id' => Course::factory(),
            'title' => ['en' => $this->faker->word],
            'explanation' => ['en' => $this->faker->sentence],
        ];
    }
}
