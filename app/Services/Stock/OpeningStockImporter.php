<?php

namespace App\Services\Stock;

use App\Enums\StockMovementType;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Support\Decimal;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use Throwable;

/**
 * Loads the opening stock count for one branch: one row per batch on the
 * shelf. All-or-nothing, like the product import: any bad row stops the
 * whole file and is reported by row number.
 */
class OpeningStockImporter
{
    public const COLUMNS = ['sku', 'lot_number', 'expires_on', 'unit', 'quantity', 'unit_cost'];

    public function __construct(private StockService $stock) {}

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array{errors: list<array{row: int, column: string, message: string}>, batches: int, units: int}
     */
    public function import(int $branchId, array $rows): array
    {
        $errors = [];
        $parsed = [];
        $products = Product::query()
            ->with('units')
            ->whereIn('sku', collect($rows)->pluck('sku')->filter()->map(fn ($sku) => trim((string) $sku))->unique()->all())
            ->get()
            ->keyBy('sku');

        foreach ($rows as $index => $row) {
            $rowNumber = $index + 2;
            $row = array_map(fn ($value) => is_string($value) ? trim($value) : $value, $row);

            if (collect($row)->filter(fn ($value) => $value !== null && $value !== '')->isEmpty()) {
                continue;
            }

            $row['expires_on'] = $this->date($row['expires_on'] ?? null);

            $validator = Validator::make($row, [
                'sku' => ['required'],
                'lot_number' => ['required', 'max:50'],
                'expires_on' => ['required', 'date'],
                'quantity' => ['required', 'integer', 'min:1'],
                'unit_cost' => ['required', 'numeric', 'min:0'],
            ]);

            foreach ($validator->errors()->messages() as $column => $messages) {
                $errors[] = ['row' => $rowNumber, 'column' => $column, 'message' => $messages[0]];
            }

            $product = $products->get(trim((string) ($row['sku'] ?? '')));

            if ($product === null) {
                $errors[] = ['row' => $rowNumber, 'column' => 'sku', 'message' => __('No product has this SKU.')];

                continue;
            }

            $unitName = trim((string) ($row['unit'] ?? ''));
            $unit = $unitName === ''
                ? $product->units->firstWhere('is_base', true)
                : $product->units->first(fn (ProductUnit $unit) => mb_strtolower($unit->name) === mb_strtolower($unitName));

            if ($unit === null) {
                $errors[] = ['row' => $rowNumber, 'column' => 'unit', 'message' => __('This product has no unit called :unit.', ['unit' => $unitName])];

                continue;
            }

            $parsed[] = [$product, $unit, $row];
        }

        if ($errors !== []) {
            return ['errors' => $errors, 'batches' => 0, 'units' => 0];
        }

        $units = 0;

        DB::transaction(function () use ($branchId, $parsed, &$units): void {
            foreach ($parsed as [$product, $unit, $row]) {
                $quantity = $unit->toBase((int) $row['quantity']);
                $costPerBase = Decimal::div((string) $row['unit_cost'], $unit->conversion_factor, Decimal::COST);

                $this->stock->receive(
                    $branchId,
                    $product,
                    (string) $row['lot_number'],
                    (string) $row['expires_on'],
                    $quantity,
                    $costPerBase,
                    StockMovementType::Opening,
                    reference: 'OPENING',
                );

                $units += $quantity;
            }
        });

        return ['errors' => [], 'batches' => count($parsed), 'units' => $units];
    }

    /**
     * Excel gives dates as serial numbers; CSV gives text.
     */
    private function date(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            if (is_numeric($value)) {
                return CarbonImmutable::instance(ExcelDate::excelToDateTimeObject((float) $value))->toDateString();
            }

            return CarbonImmutable::parse((string) $value)->toDateString();
        } catch (Throwable) {
            return (string) $value;
        }
    }
}
