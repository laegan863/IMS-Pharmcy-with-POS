<?php

namespace App\Reports;

use App\Enums\Permission;
use App\Support\Decimal;
use Illuminate\Support\Facades\DB;

/**
 * Fast movers, slow movers and dead stock in the period: base units sold
 * against stock on hand. Dead stock is on hand with no sales at all.
 */
final class MoversReport extends Report
{
    public function title(): string
    {
        return 'Fast, slow and dead stock';
    }

    public function description(): string
    {
        return 'Units sold in the period, stock on hand and days of cover per product. Dead stock has stock but no sales.';
    }

    public function permission(): Permission
    {
        return Permission::ReportsInventory;
    }

    public function group(): string
    {
        return 'Inventory';
    }

    public function columns(): array
    {
        return [
            'class' => ['Class', 'text'],
            'sku' => ['SKU', 'text'],
            'product' => ['Product', 'text'],
            'sold' => ['Sold (base units)', 'number'],
            'on_hand' => ['On hand', 'number'],
            'days_cover' => ['Days of cover', 'number'],
            'value' => ['Stock value', 'money'],
        ];
    }

    public function totalColumns(): array
    {
        return ['value'];
    }

    public function rows(ReportFilters $filters): iterable
    {
        $days = max(1, (int) $filters->start()->diffInDays($filters->end()) + 1);

        $sold = DB::table('stock_movements')
            ->where('branch_id', $filters->branchId)
            ->where('type', 'sale')
            ->whereBetween('created_at', [$filters->start(), $filters->end()])
            ->groupBy('product_id')
            ->pluck(DB::raw('-SUM(quantity)'), 'product_id');

        $stock = DB::table('stock_balances')
            ->join('batches', 'batches.id', '=', 'stock_balances.batch_id')
            ->where('stock_balances.branch_id', $filters->branchId)
            ->groupBy('stock_balances.product_id')
            ->selectRaw('stock_balances.product_id, SUM(stock_balances.quantity) as on_hand, SUM(stock_balances.quantity * batches.unit_cost) as value')
            ->get()
            ->keyBy('product_id');

        $products = DB::table('products')
            ->join('generic_names', 'generic_names.id', '=', 'products.generic_name_id')
            ->whereIn('products.id', $sold->keys()->merge($stock->keys())->unique()->all())
            ->get(['products.id', 'products.sku', 'products.brand_name', 'products.strength', 'generic_names.name as generic']);

        $ranked = $products->map(function ($product) use ($sold, $stock, $days) {
            $soldUnits = (int) ($sold[$product->id] ?? 0);
            $onHand = (int) ($stock->get($product->id)->on_hand ?? 0);

            return [
                'sku' => $product->sku,
                'product' => trim(($product->brand_name ? $product->brand_name.' ('.$product->generic.')' : $product->generic).' '.$product->strength),
                'sold' => $soldUnits,
                'on_hand' => $onHand,
                'days_cover' => $soldUnits > 0 ? (int) round($onHand / ($soldUnits / $days)) : null,
                'value' => Decimal::round((string) ($stock->get($product->id)->value ?? '0')),
            ];
        })->sortByDesc('sold')->values();

        $withSales = $ranked->where('sold', '>', 0)->values();
        $fastCut = max(1, (int) ceil($withSales->count() * 0.2));

        foreach ($ranked as $row) {
            $position = $withSales->search(fn (array $candidate) => $candidate['sku'] === $row['sku']);

            yield [
                'class' => match (true) {
                    $row['sold'] === 0 && $row['on_hand'] > 0 => 'Dead',
                    $position !== false && $position < $fastCut => 'Fast',
                    default => 'Slow',
                },
                ...$row,
            ];
        }
    }
}
