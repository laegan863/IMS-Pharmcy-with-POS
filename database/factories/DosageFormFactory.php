<?php

namespace Database\Factories;

use App\Models\DosageForm;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DosageForm>
 */
class DosageFormFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => ucfirst(fake()->unique()->word()).' '.fake()->word(),
            'is_active' => true,
        ];
    }
}
