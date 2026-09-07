<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Model>
 */
class LocationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->unique()->randomElement([
                'Mayor\'s Office',
                'Treasurer\'s Office',
                'Assessor\'s Office',
                'Engineering Office',
                'Health Office',
                'Social Welfare Office',
                'Agriculture Office',
                'Tourism Office',
                'Human Resources Office',
                'Budget Office',
                'Planning and Development Office',
                'IT Office',
            ]),
            'address' => $this->faker->address(),
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}
