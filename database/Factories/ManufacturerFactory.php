<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Model>
 */
class ManufacturerFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->unique()->company(),
            'url' => $this->faker->url(),
            'support_url' => $this->faker->url(),
            'support_phone' => $this->faker->phoneNumber(),
            'support_email' => $this->faker->unique()->safeEmail(),
        ];
    }
}
