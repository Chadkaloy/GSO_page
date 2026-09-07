<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\AssetStatusEnum;
use App\Models\Asset;
use App\Models\Category;
use App\Models\Location;
use App\Models\Manufacturer;
use App\Models\User;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Model>
 */
class AssetFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $categoryId = Category::inRandomOrder()->first()->id ?? Category::factory();
        $locationId = Location::inRandomOrder()->first()->id ?? Location::factory();
        $manufacturerId = Manufacturer::inRandomOrder()->first()->id ?? Manufacturer::factory();
        $assignedToUserId = User::inRandomOrder()->first()->id ?? User::factory(); // Can be null for unassigned
        
        $statuses = [
            AssetStatusEnum::Deployed,
            AssetStatusEnum::InStorage,
            AssetStatusEnum::Maintenance,
            AssetStatusEnum::Retired,
            AssetStatusEnum::Broken,
        ];

        return [
            'asset_tag' => 'AST-' . $this->faker->unique()->randomNumber(5, true), // Unique asset tag
            'name' => $this->faker->word() . ' ' . $this->faker->randomElement(['Laptop', 'Monitor', 'Printer', 'Server', 'Switch']),
            'serial_number' => $this->faker->unique()->uuid(),
            'model_name' => $this->faker->bothify('Model-###-???'),
            'purchase_date' => $this->faker->dateTimeBetween('-3 years', 'now')->format('Y-m-d'),
            'purchase_price' => $this->faker->randomFloat(2, 50, 5000),
            'status' => $this->faker->randomElement($statuses), // Assign a random status from the Enum
            'notes' => $this->faker->sentence(),
            'category_id' => $categoryId,
            'location_id' => $locationId,
            'manufacturer_id' => $manufacturerId,
            // 70% chance an asset is assigned, otherwise null
            'assigned_to_user_id' => $this->faker->boolean(70) ? $assignedToUserId : null,
        ];
    }
}
