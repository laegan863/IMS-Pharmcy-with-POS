<?php

namespace App\Services\Purchasing;

use App\Models\Product;
use App\Models\ProductUnit;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Loads the products and units named on document lines in two queries and
 * checks each unit belongs to its product.
 */
class LineResolver
{
    /** @var Collection<int, Product> */
    private Collection $products;

    /**
     * @param  list<array<string, mixed>>  $lines
     */
    public function __construct(array $lines)
    {
        $this->products = Product::query()
            ->with(['units', 'genericName', 'dosageForm'])
            ->whereIn('id', array_map(fn (array $line) => (int) ($line['product_id'] ?? 0), $lines))
            ->get()
            ->keyBy('id');
    }

    /**
     * @return array{0: Product, 1: ProductUnit}
     */
    public function resolve(int $index, int $productId, int $unitId, bool $mustBePurchasable = true): array
    {
        $product = $this->products->get($productId);
        $unit = $product?->units->firstWhere('id', $unitId);

        if ($product === null || $unit === null) {
            throw ValidationException::withMessages(["lines.{$index}.product_unit_id" => __('Choose a unit of this product.')]);
        }

        if ($mustBePurchasable && ! $unit->is_purchasable) {
            throw ValidationException::withMessages(["lines.{$index}.product_unit_id" => __(':unit is not set up to be bought.', ['unit' => $unit->name])]);
        }

        return [$product, $unit];
    }
}
