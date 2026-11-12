<?php

namespace App\Services\Catalog;

use App\Enums\ProductClassification;
use App\Enums\StorageCondition;
use App\Enums\TaxClass;
use App\Models\Category;
use App\Models\DosageForm;
use App\Models\Product;
use App\Models\ProductBarcode;
use App\Models\ProductUnit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

/**
 * Imports the product list from a spreadsheet. Every row is checked first;
 * if any row has an error nothing is saved and the report lists every
 * problem by row number, so the file can be fixed and uploaded again.
 *
 * Rows whose SKU already exists update that product.
 */
class ProductImporter
{
    /** Headings of the template, in order. */
    public const COLUMNS = [
        'sku', 'generic_name', 'brand_name', 'strength', 'dosage_form', 'manufacturer', 'category',
        'classification', 'tax_class', 'fda_registration_number', 'storage_condition', 'reorder_point',
        'base_unit', 'base_price', 'base_barcode',
        'unit2_name', 'unit2_factor', 'unit2_price', 'unit2_barcode',
        'unit3_name', 'unit3_factor', 'unit3_price', 'unit3_barcode',
    ];

    public const MAX_ROWS = 20000;

    public function __construct(private ProductService $products) {}

    /**
     * @param  list<array<string, mixed>>  $rows  rows keyed by heading
     * @return array{errors: list<array{row: int, column: string, message: string}>, created: int, updated: int}
     */
    public function import(array $rows, bool $canSetPrices): array
    {
        $errors = [];
        $parsed = [];
        $seenSkus = [];
        $seenBarcodes = [];

        if (count($rows) > self::MAX_ROWS) {
            return ['errors' => [['row' => 0, 'column' => '', 'message' => __('The file has more than :max rows.', ['max' => self::MAX_ROWS])]], 'created' => 0, 'updated' => 0];
        }

        foreach ($rows as $index => $row) {
            $rowNumber = $index + 2; // Row 1 is the heading.
            $row = array_map(fn ($value) => is_string($value) ? trim($value) : $value, $row);

            if (collect($row)->filter(fn ($value) => $value !== null && $value !== '')->isEmpty()) {
                continue;
            }

            $row['classification'] = $this->normalizeEnum($row['classification'] ?? null);
            $row['tax_class'] = $this->normalizeEnum($row['tax_class'] ?? null);
            $row['storage_condition'] = $this->normalizeEnum($row['storage_condition'] ?? null) ?: StorageCondition::Room->value;

            $validator = Validator::make($row, [
                'sku' => ['required', 'max:50'],
                'generic_name' => ['required', 'string', 'max:150'],
                'brand_name' => ['nullable', 'max:150'],
                'strength' => ['nullable', 'max:50'],
                'dosage_form' => ['nullable', 'max:150'],
                'manufacturer' => ['nullable', 'max:150'],
                'category' => ['nullable', 'max:150'],
                'classification' => ['required', new Enum(ProductClassification::class)],
                'tax_class' => ['required', new Enum(TaxClass::class)],
                'storage_condition' => ['required', new Enum(StorageCondition::class)],
                'reorder_point' => ['nullable', 'integer', 'min:0'],
                'base_unit' => ['required', 'max:30'],
                'base_price' => ['nullable', 'numeric', 'min:0'],
                'base_barcode' => ['nullable', 'max:64'],
                'unit2_name' => ['nullable', 'max:30', Rule::requiredIf(filled($row['unit2_factor'] ?? null))],
                'unit2_factor' => ['nullable', 'integer', 'min:2', Rule::requiredIf(filled($row['unit2_name'] ?? null))],
                'unit2_price' => ['nullable', 'numeric', 'min:0'],
                'unit2_barcode' => ['nullable', 'max:64'],
                'unit3_name' => ['nullable', 'max:30', Rule::requiredIf(filled($row['unit3_factor'] ?? null))],
                'unit3_factor' => ['nullable', 'integer', 'min:2', Rule::requiredIf(filled($row['unit3_name'] ?? null))],
                'unit3_price' => ['nullable', 'numeric', 'min:0'],
                'unit3_barcode' => ['nullable', 'max:64'],
            ]);

            foreach ($validator->errors()->messages() as $column => $messages) {
                $errors[] = ['row' => $rowNumber, 'column' => $column, 'message' => $messages[0]];
            }

            $sku = (string) ($row['sku'] ?? '');

            if ($sku !== '' && isset($seenSkus[$sku])) {
                $errors[] = ['row' => $rowNumber, 'column' => 'sku', 'message' => __('Duplicate of row :row.', ['row' => $seenSkus[$sku]])];
            }

            $seenSkus[$sku] = $rowNumber;

            foreach (['base_barcode', 'unit2_barcode', 'unit3_barcode'] as $column) {
                $code = (string) ($row[$column] ?? '');

                if ($code === '') {
                    continue;
                }

                if (isset($seenBarcodes[$code])) {
                    $errors[] = ['row' => $rowNumber, 'column' => $column, 'message' => __('Barcode also on row :row.', ['row' => $seenBarcodes[$code]])];
                }

                $seenBarcodes[$code] = $rowNumber;
            }

            $parsed[$rowNumber] = $row;
        }

        $errors = [...$errors, ...$this->barcodeConflicts($parsed)];

        if ($errors !== []) {
            return ['errors' => $errors, 'created' => 0, 'updated' => 0];
        }

        $created = 0;
        $updated = 0;

        DB::transaction(function () use ($parsed, $canSetPrices, &$created, &$updated): void {
            foreach ($parsed as $row) {
                $product = Product::query()->where('sku', (string) $row['sku'])->first();
                $product ? $updated++ : $created++;

                $this->products->save(
                    $product,
                    $this->attributes($row),
                    $this->units($row, $product),
                    $this->barcodes($row),
                    $canSetPrices,
                );
            }
        });

        return ['errors' => [], 'created' => $created, 'updated' => $updated];
    }

    /**
     * Barcodes in the file that already belong to a different product.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return list<array{row: int, column: string, message: string}>
     */
    private function barcodeConflicts(array $rows): array
    {
        $codes = [];

        foreach ($rows as $rowNumber => $row) {
            foreach (['base_barcode', 'unit2_barcode', 'unit3_barcode'] as $column) {
                if (filled($row[$column] ?? null)) {
                    $codes[(string) $row[$column]] = [$rowNumber, $column, (string) $row['sku']];
                }
            }
        }

        if ($codes === []) {
            return [];
        }

        $conflicts = [];

        ProductBarcode::query()
            ->with('product:id,sku')
            ->whereIn('code', array_keys($codes))
            ->each(function (ProductBarcode $barcode) use ($codes, &$conflicts): void {
                [$rowNumber, $column, $sku] = $codes[$barcode->code];

                if ($barcode->product->sku !== $sku) {
                    $conflicts[] = ['row' => $rowNumber, 'column' => $column, 'message' => __('Barcode already belongs to :sku.', ['sku' => $barcode->product->sku])];
                }
            });

        return $conflicts;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function attributes(array $row): array
    {
        $classification = ProductClassification::from((string) $row['classification']);

        return [
            'sku' => (string) $row['sku'],
            'generic_name' => (string) $row['generic_name'],
            'brand_name' => $row['brand_name'] ?: null,
            'strength' => $row['strength'] ?: null,
            'dosage_form_id' => filled($row['dosage_form'] ?? null) ? DosageForm::findOrCreateByName((string) $row['dosage_form'])->id : null,
            'manufacturer' => $row['manufacturer'] ?: null,
            'category_id' => filled($row['category'] ?? null) ? Category::findOrCreateByName((string) $row['category'])->id : null,
            'classification' => $classification->value,
            'fda_registration_number' => $row['fda_registration_number'] ?: null,
            'tax_class' => (string) $row['tax_class'],
            'statutory_discount_eligible' => $classification !== ProductClassification::NonDrug,
            'storage_condition' => (string) $row['storage_condition'],
            'reorder_point' => (int) ($row['reorder_point'] ?? 0),
            'is_active' => true,
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     * @return list<array<string, mixed>>
     */
    private function units(array $row, ?Product $product): array
    {
        $existing = $product?->units()->get()->keyBy(fn (ProductUnit $unit) => mb_strtolower($unit->name));
        $units = [[
            'name' => (string) $row['base_unit'],
            'conversion_factor' => 1,
            'is_base' => true,
            'price' => $row['base_price'] ?? null,
        ]];

        foreach (['unit2', 'unit3'] as $prefix) {
            if (filled($row["{$prefix}_name"] ?? null)) {
                $units[] = [
                    'name' => (string) $row["{$prefix}_name"],
                    'conversion_factor' => (int) $row["{$prefix}_factor"],
                    'is_base' => false,
                    'price' => $row["{$prefix}_price"] ?? null,
                ];
            }
        }

        return array_map(function (array $unit) use ($existing): array {
            $match = $existing?->get(mb_strtolower($unit['name']));

            return [...$unit, 'id' => $match?->id, 'is_sellable' => true, 'is_purchasable' => true];
        }, $units);
    }

    /**
     * @param  array<string, mixed>  $row
     * @return list<array{code: string, unit: string}>
     */
    private function barcodes(array $row): array
    {
        $barcodes = [];

        foreach (['base' => 'base_unit', 'unit2' => 'unit2_name', 'unit3' => 'unit3_name'] as $prefix => $unitColumn) {
            if (filled($row["{$prefix}_barcode"] ?? null) && filled($row[$unitColumn] ?? null)) {
                $barcodes[] = ['code' => (string) $row["{$prefix}_barcode"], 'unit' => (string) $row[$unitColumn]];
            }
        }

        return $barcodes;
    }

    /**
     * Accepts "VAT-exempt", "vat exempt" or "vat_exempt", and "Rx" or "rx".
     */
    private function normalizeEnum(mixed $value): string
    {
        return str_replace([' ', '-'], '_', mb_strtolower(trim((string) $value)));
    }
}
