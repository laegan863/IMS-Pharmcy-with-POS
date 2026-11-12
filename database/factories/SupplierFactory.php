<?php

namespace Database\Factories;

use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Supplier>
 */
class SupplierFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => strtoupper(fake()->unique()->bothify('SUP-###')),
            'name' => fake()->company().' Pharma Distributors',
            'tin' => fake()->numerify('###-###-###-00000'),
            'address' => fake()->address(),
            'fda_license_number' => fake()->bothify('LTO-#######'),
            'fda_license_expires_on' => now()->addYear()->toDateString(),
            'payment_terms_days' => 30,
            'contact_person' => fake()->name(),
            'phone' => fake()->phoneNumber(),
            'email' => fake()->companyEmail(),
            'is_active' => true,
        ];
    }
}
