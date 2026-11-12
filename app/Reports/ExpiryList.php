<?php

namespace App\Reports;

use App\Enums\Permission;
use App\Support\Decimal;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Batches with stock that expire by the "to" date (or have expired),
 * soonest first.
 */
final class ExpiryList extends Report
{
    public function title(): string
    {
        return 'Expiry list';
    }

    public function description(): string
    {
        return 'Batches on hand that expire by the end date, or already have, with their value at cost.';
    }

    public function permission(): Permission
    {
        return Permission::ReportsInventory;
    }

    public function group(): string
    {
        return 'Inventory';
    }

    public function defaultRange(): array
    {
        return [CarbonImmutable::today(), CarbonImmutable::today()->addDays(90)];
    }

    public function columns(): array
    {
        return [
            'expires_on' => ['Expiry', 'date'],
            'days' => ['Days left', 'number'],
            'product' => ['Product', 'text'],
            'lot' => ['Lot', 'text'],
            'status' => ['Status', 'text'],
            'quantity' => ['On hand', 'number'],
            'value' => ['Value at cost', 'money'],
        ];
    }

    public function rows(ReportFilters $filters): iterable
    {
        $rows = DB::table('stock_balances')
            ->join('batches', 'batches.id', '=', 'stock_balances.batch_id')
            ->join('products', 'products.id', '=', 'batches.product_id')
            ->join('generic_names', 'generic_names.id', '=', 'products.generic_name_id')
            ->where('stock_balances.branch_id', $filters->branchId)
            ->where('stock_balances.quantity', '>', 0)
            ->whereDate('batches.expires_on', '<=', $filters->to->toDateString())
            ->orderBy('batches.expires_on')
            ->get(['batches.expires_on', 'batches.lot_number', 'batches.status', 'batches.unit_cost', 'stock_balances.quantity', 'products.brand_name', 'products.strength', 'generic_names.name as generic']);

        foreach ($rows as $row) {
            $expires = CarbonImmutable::parse((string) $row->expires_on);

            yield [
                'expires_on' => $expires->toDateString(),
                'days' => (int) CarbonImmutable::today()->diffInDays($expires, false),
                'product' => trim(($row->brand_name ? $row->brand_name.' ('.$row->generic.')' : $row->generic).' '.$row->strength),
                'lot' => $row->lot_number,
                'status' => $row->status,
                'quantity' => (int) $row->quantity,
                'value' => Decimal::mul((int) $row->quantity, (string) $row->unit_cost),
            ];
        }
    }
}
