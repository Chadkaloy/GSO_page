<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Model>
 */
class CategoryFactory extends Factory
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
                'Laptops',
                'Desktops',
                'Smartphones',
                'Tablets',
                'Printers',
                'Scanners',
                'Networking Devices',
                'Storage Devices',
                'Monitors',
                'Projectors',
                'Cameras',
                'Accessories',
            ]),
            'description' => $this->faker->sentence(),
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}
