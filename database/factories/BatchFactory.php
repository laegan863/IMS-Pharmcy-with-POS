<?php

namespace Database\Factories;

use App\Enums\BatchStatus;
use App\Models\Batch;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A batch record only. To put stock in a branch use StockService::receive().
 *
 * @extends Factory<Batch>
 */
class BatchFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'lot_number' => strtoupper(fake()->unique()->bothify('LOT-####??')),
            'expires_on' => now()->addYear()->toDateString(),
            'unit_cost' => '4.0000',
            'status' => BatchStatus::Active,
        ];
    }
}
