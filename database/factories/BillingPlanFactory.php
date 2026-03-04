<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\BillingPlan>
 */
class BillingPlanFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->words(3, true),
            'description' => $this->faker->sentence(),
            'price' => $this->faker->randomFloat(2, 10, 1000),
            'currency' => 'USD',
            'billing_type' => 'one_time',
            'billing_interval' => null,
            'access_type' => 'limited',
            'access_duration_days' => 30,
            'is_active' => true,
        ];
    }
}
