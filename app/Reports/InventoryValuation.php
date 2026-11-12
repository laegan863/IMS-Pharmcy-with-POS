<?php

namespace App\Reports;

use App\Enums\Permission;
use App\Support\Decimal;
use Illuminate\Support\Facades\DB;

/**
 * Stock on hand at cost: each batch's quantity times its batch cost.
 */
final class InventoryValuation extends Report
{
    public function title(): string
    {
        return 'Inventory valuation';
    }

    public function description(): string
    {
        return 'On-hand quantity and value at batch cost per product, as of now.';
    }

    public function permission(): Permission
    {
        return Permission::ReportsInventory;
    }

    public function group(): string
    {
        return 'Inventory';
    }

    public function usesDates(): bool
    {
        return false;
    }

    public function columns(): array
    {
        return [
            'sku' => ['SKU', 'text'],
            'product' => ['Product', 'text'],
            'on_hand' => ['On hand (base units)', 'number'],
            'sellable' => ['Sellable', 'number'],
            'average_cost' => ['Average cost', 'money'],
            'value' => ['Value at cost', 'money'],
        ];
    }

    public function rows(ReportFilters $filters): iterable
    {
        $rows = DB::table('stock_balances')
            ->join('batches', 'batches.id', '=', 'stock_balances.batch_id')
            ->join('products', 'products.id', '=', 'stock_balances.product_id')
            ->join('generic_names', 'generic_names.id', '=', 'products.generic_name_id')
            ->where('stock_balances.branch_id', $filters->branchId)
            ->where('stock_balances.quantity', '>', 0)
            ->groupBy('products.id', 'products.sku', 'products.brand_name', 'products.strength', 'generic_names.name')
            ->selectRaw('products.sku, products.brand_name, products.strength, generic_names.name as generic')
            ->selectRaw('SUM(stock_balances.quantity) as on_hand, SUM(stock_balances.quantity * batches.unit_cost) as value')
            ->selectRaw("SUM(CASE WHEN batches.status = 'active' AND batches.expires_on >= ? THEN stock_balances.quantity ELSE 0 END) as sellable", [today()->toDateString()])
            ->orderBy('products.sku')
            ->get();

        foreach ($rows as $row) {
            $value = Decimal::round((string) $row->value);

            yield [
                'sku' => $row->sku,
                'product' => trim(($row->brand_name ? $row->brand_name.' ('.$row->generic.')' : $row->generic).' '.$row->strength),
                'on_hand' => (int) $row->on_hand,
                'sellable' => (int) $row->sellable,
                'average_cost' => Decimal::div($value, (int) $row->on_hand),
                'value' => $value,
            ];
        }
    }

    public function totalColumns(): array
    {
        return ['value'];
    }
}
