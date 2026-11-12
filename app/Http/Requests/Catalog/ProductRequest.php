<?php

namespace App\Http\Requests\Catalog;

use App\Enums\Permission;
use App\Enums\ProductClassification;
use App\Enums\StorageCondition;
use App\Enums\TaxClass;
use App\Models\Product;
use App\Models\ProductBarcode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Validator;

/**
 * The product form: one base unit (tablet, ml, piece) that stock is counted
 * in, any number of larger units, and barcodes that each point at a unit.
 */
class ProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(Permission::ProductsManage->value) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $product = $this->route('product');

        return [
            'sku' => ['required', 'string', 'max:50', Rule::unique('products', 'sku')->ignore($product instanceof Product ? $product->id : null)],
            'generic_name' => ['required', 'string', 'max:150'],
            'brand_name' => ['nullable', 'string', 'max:150'],
            'strength' => ['nullable', 'string', 'max:50'],
            'dosage_form_id' => ['nullable', 'integer', Rule::exists('dosage_forms', 'id')],
            'manufacturer' => ['nullable', 'string', 'max:150'],
            'category_id' => ['nullable', 'integer', Rule::exists('categories', 'id')],
            'classification' => ['required', new Enum(ProductClassification::class)],
            'fda_registration_number' => ['nullable', 'string', 'max:50'],
            'tax_class' => ['required', new Enum(TaxClass::class)],
            'tax_class_effective_from' => ['nullable', 'date'],
            'statutory_discount_eligible' => ['boolean'],
            'storage_condition' => ['required', new Enum(StorageCondition::class)],
            'reorder_point' => ['required', 'integer', 'min:0', 'max:10000000'],
            'max_level' => ['nullable', 'integer', 'min:0', 'max:10000000', 'gte:reorder_point'],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['boolean'],

            'units' => ['required', 'array', 'min:1', 'max:6'],
            'units.*.id' => ['nullable', 'integer'],
            'units.*.name' => ['required', 'string', 'max:30', 'distinct:ignore_case'],
            'units.*.conversion_factor' => ['required', 'integer', 'min:1', 'max:100000'],
            'units.*.is_base' => ['boolean'],
            'units.*.is_sellable' => ['boolean'],
            'units.*.is_purchasable' => ['boolean'],
            'units.*.price' => ['nullable', 'numeric', 'min:0', 'max:9999999999'],

            'barcodes' => ['array', 'max:20'],
            'barcodes.*.code' => ['required', 'string', 'max:64', 'distinct'],
            'barcodes.*.unit' => ['required', 'string'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'units.*.name' => 'unit name',
            'units.*.conversion_factor' => 'conversion',
            'units.*.price' => 'price',
            'barcodes.*.code' => 'barcode',
            'barcodes.*.unit' => 'barcode unit',
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $units = collect((array) $this->input('units', []));
                $bases = $units->filter(fn ($unit) => is_array($unit) && filter_var($unit['is_base'] ?? false, FILTER_VALIDATE_BOOLEAN));

                if ($bases->count() !== 1) {
                    $validator->errors()->add('units', __('Mark exactly one unit as the base unit that stock is counted in.'));
                }

                foreach ($bases as $index => $unit) {
                    if ((int) ($unit['conversion_factor'] ?? 0) !== 1) {
                        $validator->errors()->add("units.{$index}.conversion_factor", __('The base unit must have a conversion of 1.'));
                    }
                }

                $product = $this->route('product');

                foreach ((array) $this->input('barcodes', []) as $index => $barcode) {
                    $code = is_array($barcode) ? trim((string) ($barcode['code'] ?? '')) : '';

                    if ($code === '') {
                        continue;
                    }

                    $owner = ProductBarcode::query()->where('code', $code)->value('product_id');

                    if ($owner !== null && (! $product instanceof Product || $owner !== $product->id)) {
                        $validator->errors()->add("barcodes.{$index}.code", __('This barcode belongs to another product.'));
                    }
                }
            },
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function productAttributes(): array
    {
        return $this->safe()->except(['units', 'barcodes']);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function units(): array
    {
        return array_values((array) $this->validated('units'));
    }

    /**
     * @return list<array{code: string, unit: string}>
     */
    public function barcodes(): array
    {
        return array_values((array) $this->validated('barcodes', []));
    }
}
