<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Carbon\Carbon;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Certification>
 */
class CertificationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $violations = [
            'Illegal parking in a no-parking zone',
            'Littering in public places',
            'Noise violation during quiet hours',
            'Obstructing pedestrian walkway',
            'Unauthorized vending in restricted area',
            'Smoking in non-smoking area',
            'Failure to segregate waste properly',
            'Illegal dumping of construction materials',
            'Operating business without permit',
            'Violation of building code regulations',
            'Unauthorized display of signage',
            'Violation of sanitation standards',
            'Illegal connection to utilities',
            'Obstruction of public drainage',
            'Unauthorized excavation',
        ];

        $cities = [
            'Quezon City', 'Manila', 'Makati', 'Taguig', 'Pasig',
            'Mandaluyong', 'Pasay', 'Paranaque', 'Las Pinas', 'Marikina',
            'Valenzuela', 'Malabon', 'Caloocan', 'Navotas', 'San Juan',
        ];

        $violationDate = $this->faker->dateTimeBetween('-1 year', 'now');
        $issuedDate = Carbon::parse($violationDate)->addDays($this->faker->numberBetween(1, 7));

        return [
            'name' => $this->faker->name(),
            'address' => $this->faker->streetAddress() . ', Barangay ' . $this->faker->firstName() . ', ' . $this->faker->randomElement($cities),
            'violation' => $this->faker->randomElement($violations),
            'date_of_violation' => $violationDate,
            'ordinance_no' => 'ORD-' . $this->faker->year() . '-' . $this->faker->numberBetween(1000, 9999),
            'amount' => $this->faker->randomElement([500, 750, 800, 1000, 1200, 1500, 2000, 2500, 3000, 5000]),
            'receipt_no' => 'RC-' . $this->faker->year() . '-' . $this->faker->numberBetween(1000, 9999),
            'issued_date' => $issuedDate,
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}
