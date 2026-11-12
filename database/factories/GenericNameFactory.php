<?php

namespace Database\Factories;

use App\Models\GenericName;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GenericName>
 */
class GenericNameFactory extends Factory
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
