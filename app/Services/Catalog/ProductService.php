<?php

namespace App\Services\Catalog;

use App\Models\GenericName;
use App\Models\Manufacturer;
use App\Models\Product;
use App\Models\ProductBarcode;
use App\Models\ProductUnit;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Saves a product with its units and barcodes in one transaction.
 *
 * Input shape (already validated by ProductRequest):
 *   attributes: product columns, plus generic_name and manufacturer as names
 *   units: [{id?, name, conversion_factor, is_base, is_sellable, is_purchasable, price?}]
 *   barcodes: [{code, unit}] where unit is a unit name
 */
class ProductService
{
    /**
     * @param  array<string, mixed>  $attributes
     * @param  list<array<string, mixed>>  $units
     * @param  list<array{code: string, unit: string}>  $barcodes
     */
    public function save(?Product $product, array $attributes, array $units, array $barcodes, bool $canSetPrices): Product
    {
        return DB::transaction(function () use ($product, $attributes, $units, $barcodes, $canSetPrices): Product {
            $attributes['generic_name_id'] = GenericName::findOrCreateByName((string) $attributes['generic_name'])->id;
            $attributes['manufacturer_id'] = filled($attributes['manufacturer'] ?? null)
                ? Manufacturer::findOrCreateByName((string) $attributes['manufacturer'])->id
                : null;
            unset($attributes['generic_name'], $attributes['manufacturer']);

            $product ??= new Product;
            $product->fill($attributes)->save();

            $savedUnits = $this->syncUnits($product, $units, $canSetPrices);
            $this->syncBarcodes($product, $barcodes, $savedUnits);

            return $product->refresh();
        });
    }

    /**
     * @param  list<array<string, mixed>>  $units
     * @return array<string, ProductUnit> keyed by lower-case unit name
     */
    private function syncUnits(Product $product, array $units, bool $canSetPrices): array
    {
        $existing = $product->units()->get()->keyBy('id');
        $kept = [];
        $saved = [];

        foreach ($units as $data) {
            $id = isset($data['id']) ? (int) $data['id'] : null;
            $unit = $id !== null ? $existing->get($id) : null;

            if ($id !== null && $unit === null) {
                throw ValidationException::withMessages(['units' => __('A unit does not belong to this product.')]);
            }

            $unit ??= new ProductUnit(['product_id' => $product->id]);
            $isBase = (bool) ($data['is_base'] ?? false);

            $unit->fill([
                'name' => trim((string) $data['name']),
                'conversion_factor' => $isBase ? 1 : (int) $data['conversion_factor'],
                'is_base' => $isBase,
                'is_sellable' => (bool) ($data['is_sellable'] ?? true),
                'is_purchasable' => (bool) ($data['is_purchasable'] ?? true),
            ]);

            if ($canSetPrices) {
                $unit->price = isset($data['price']) && $data['price'] !== '' ? number_format((float) $data['price'], 2, '.', '') : null;
            }

            $unit->save();
            $kept[] = $unit->id;
            $saved[mb_strtolower($unit->name)] = $unit;
        }

        foreach ($existing->except($kept) as $removed) {
            if ($removed->isInUse()) {
                throw ValidationException::withMessages([
                    'units' => __('The unit :name is used on documents and cannot be removed. Mark it not sellable instead.', ['name' => $removed->name]),
                ]);
            }

            $removed->delete();
        }

        return $saved;
    }

    /**
     * @param  list<array{code: string, unit: string}>  $barcodes
     * @param  array<string, ProductUnit>  $units
     */
    private function syncBarcodes(Product $product, array $barcodes, array $units): void
    {
        $codes = [];

        foreach ($barcodes as $index => $data) {
            $code = trim($data['code']);
            $unit = $units[mb_strtolower(trim($data['unit']))] ?? null;

            if ($unit === null) {
                throw ValidationException::withMessages(["barcodes.{$index}.unit" => __('Choose one of this product\'s units.')]);
            }

            ProductBarcode::query()->updateOrCreate(
                ['code' => $code],
                ['product_id' => $product->id, 'product_unit_id' => $unit->id],
            );
            $codes[] = $code;
        }

        $product->barcodes()->whereNotIn('code', $codes)->get()->each->delete();
    }
}
