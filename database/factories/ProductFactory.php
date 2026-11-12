<?php

namespace Database\Factories;

use App\Enums\ProductClassification;
use App\Enums\StorageCondition;
use App\Enums\TaxClass;
use App\Models\DosageForm;
use App\Models\GenericName;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Products come with a base unit "Tablet" (factor 1, PHP 5.00) and a
 * "Box" of 100 (PHP 450.00) unless withoutUnits() is used.
 *
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'sku' => strtoupper(fake()->unique()->bothify('SKU-#####')),
            'generic_name_id' => GenericName::factory(),
            'brand_name' => ucfirst(fake()->unique()->word()),
            'strength' => fake()->randomElement(['250 mg', '500 mg', '10 mg', '5 mg/5 mL']),
            'dosage_form_id' => DosageForm::factory(),
            'classification' => ProductClassification::Otc,
            'tax_class' => TaxClass::Vatable,
            'statutory_discount_eligible' => true,
            'storage_condition' => StorageCondition::Room,
            'reorder_point' => 100,
            'is_active' => true,
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (Product $product): void {
            if ($product->units()->exists() || $this->withoutUnits) {
                return;
            }

            $product->units()->create(['name' => 'Tablet', 'conversion_factor' => 1, 'is_base' => true, 'price' => '5.00']);
            $product->units()->create(['name' => 'Box', 'conversion_factor' => 100, 'is_base' => false, 'price' => '450.00']);
        });
    }

    private bool $withoutUnits = false;

    public function withoutUnits(): static
    {
        $factory = clone $this;
        $factory->withoutUnits = true;

        return $factory;
    }

    public function rx(): static
    {
        return $this->state(['classification' => ProductClassification::Rx]);
    }

    public function vatExempt(): static
    {
        return $this->state(['tax_class' => TaxClass::VatExempt]);
    }
}
