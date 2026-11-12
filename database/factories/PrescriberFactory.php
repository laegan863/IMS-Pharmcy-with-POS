<?php

namespace Database\Factories;

use App\Models\Prescriber;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Prescriber>
 */
class PrescriberFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Dr. '.fake()->name(),
            'prc_license_number' => fake()->unique()->numerify('#######'),
            'ptr_number' => fake()->numerify('#######'),
            'specialty' => fake()->randomElement(['Internal Medicine', 'Pediatrics', 'Family Medicine']),
            'is_active' => true,
        ];
    }

    public function withS2(): static
    {
        return $this->state(fn () => ['s2_license_number' => fake()->numerify('S2-#####')]);
    }
}
