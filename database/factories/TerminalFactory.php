<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\Terminal;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Terminal>
 */
class TerminalFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'branch_id' => Branch::factory(),
            'code' => strtoupper(fake()->unique()->bothify('POS-##')),
            'name' => 'Counter '.fake()->numberBetween(1, 9),
            'machine_identification_number' => fake()->unique()->numerify('##############'),
            'permit_to_use_number' => fake()->numerify('FP###########'),
            'serial_number' => strtoupper(fake()->bothify('SN-????-#####')),
            'is_active' => true,
        ];
    }
}
