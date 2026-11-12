<?php

namespace Database\Factories;

use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Customer>
 */
class CustomerFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'phone' => fake()->phoneNumber(),
            'address' => fake()->address(),
            'is_active' => true,
        ];
    }

    public function senior(): static
    {
        return $this->state(fn () => [
            'birthdate' => now()->subYears(68)->toDateString(),
            'senior_citizen_id' => fake()->unique()->numerify('SC-########'),
        ]);
    }

    public function pwd(): static
    {
        return $this->state(fn () => ['pwd_id' => fake()->unique()->numerify('PWD-########')]);
    }
}
