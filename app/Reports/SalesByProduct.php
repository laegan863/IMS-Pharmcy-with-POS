<?php

namespace App\Reports;

use App\Enums\Permission;
use App\Support\Decimal;
use Illuminate\Support\Facades\DB;

/**
 * Quantity and net sales per product. With the profit permission it also
 * shows cost and gross profit (sales less VAT less cost of the batches sold).
 */
final class SalesByProduct extends Report
{
    public function __construct(private bool $withProfit = false) {}

    public function title(): string
    {
        return $this->withProfit ? 'Gross profit by product' : 'Sales by product';
    }

    public function description(): string
    {
        return $this->withProfit
            ? 'Net sales without VAT, cost of the batches sold, gross profit and margin per product. Refunds are not deducted.'
            : 'Base units sold and net sales per product, best sellers first.';
    }

    public function permission(): Permission
    {
        return $this->withProfit ? Permission::ReportsProfit : Permission::ReportsSales;
    }

    public function group(): string
    {
        return 'Sales';
    }

    public function columns(): array
    {
        $columns = [
            'sku' => ['SKU', 'text'],
            'product' => ['Product', 'text'],
            'quantity' => ['Base units sold', 'number'],
            'net' => ['Net sales', 'money'],
        ];

        if ($this->withProfit) {
            $columns += [
                'net_ex_vat' => ['Sales ex-VAT', 'money'],
                'cost' => ['Cost', 'money'],
                'profit' => ['Gross profit', 'money'],
                'margin' => ['Margin %', 'number'],
            ];
        }

        return $columns;
    }

    public function rows(ReportFilters $filters): iterable
    {
        $rows = DB::table('sale_lines')
            ->join('sales', 'sales.id', '=', 'sale_lines.sale_id')
            ->join('products', 'products.id', '=', 'sale_lines.product_id')
            ->join('generic_names', 'generic_names.id', '=', 'products.generic_name_id')
            ->where('sales.branch_id', $filters->branchId)
            ->where('sales.status', '<>', 'voided')
            ->whereBetween('sales.completed_at', [$filters->start(), $filters->end()])
            ->groupBy('products.id', 'products.sku', 'products.brand_name', 'products.strength', 'generic_names.name')
            ->selectRaw('products.sku, products.brand_name, products.strength, generic_names.name as generic')
            ->selectRaw('SUM(sale_lines.quantity_base) as quantity, SUM(sale_lines.line_total) as net, SUM(sale_lines.vat_amount) as vat, SUM(sale_lines.cost_total) as cost')
            ->orderByDesc('net')
            ->get();

        foreach ($rows as $row) {
            $exVat = Decimal::sub((string) $row->net, (string) $row->vat);
            $cost = Decimal::round((string) $row->cost);
            $profit = Decimal::sub($exVat, $cost);

            yield [
                'sku' => $row->sku,
                'product' => trim(($row->brand_name ? $row->brand_name.' ('.$row->generic.')' : $row->generic).' '.$row->strength),
                'quantity' => (int) $row->quantity,
                'net' => Decimal::round((string) $row->net),
                'net_ex_vat' => $exVat,
                'cost' => $cost,
                'profit' => $profit,
                'margin' => Decimal::isZero($exVat) ? '0' : Decimal::mul(Decimal::div($profit, $exVat, 6), '100', 1),
            ];
        }
    }
}
