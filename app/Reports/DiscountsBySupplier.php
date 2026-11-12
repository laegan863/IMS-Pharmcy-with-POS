<?php

namespace App\Reports;

use App\Enums\Permission;
use App\Support\Decimal;
use Illuminate\Support\Facades\DB;

/**
 * Senior citizen and PWD discounts granted, by product and the supplier of
 * the batch sold, for claiming the shared cost under RR 7-2026.
 */
final class DiscountsBySupplier extends Report
{
    public function title(): string
    {
        return 'Statutory discounts by product and supplier';
    }

    public function description(): string
    {
        return 'Senior citizen and PWD discounts granted per product and the supplier of the batch sold (RR 7-2026).';
    }

    public function permission(): Permission
    {
        return Permission::ComplianceReports;
    }

    public function group(): string
    {
        return 'Compliance';
    }

    public function columns(): array
    {
        return [
            'supplier' => ['Supplier', 'text'],
            'product' => ['Product', 'text'],
            'type' => ['Discount', 'text'],
            'quantity' => ['Base units', 'number'],
            'discount' => ['Discount granted', 'money'],
        ];
    }

    public function rows(ReportFilters $filters): iterable
    {
        // Discount per line is split across the batches it drew from by quantity.
        $rows = DB::table('sale_line_batches')
            ->join('sale_lines', 'sale_lines.id', '=', 'sale_line_batches.sale_line_id')
            ->join('sales', 'sales.id', '=', 'sale_lines.sale_id')
            ->join('batches', 'batches.id', '=', 'sale_line_batches.batch_id')
            ->leftJoin('suppliers', 'suppliers.id', '=', 'batches.supplier_id')
            ->join('products', 'products.id', '=', 'sale_lines.product_id')
            ->join('generic_names', 'generic_names.id', '=', 'products.generic_name_id')
            ->where('sales.branch_id', $filters->branchId)
            ->where('sales.status', '<>', 'voided')
            ->where('sale_lines.statutory_discount_applied', true)
            ->whereBetween('sales.completed_at', [$filters->start(), $filters->end()])
            ->groupBy('suppliers.name', 'products.id', 'products.brand_name', 'products.strength', 'generic_names.name', 'sales.discount_type')
            ->selectRaw("COALESCE(suppliers.name, 'Unknown (opening stock)') as supplier, products.brand_name, products.strength, generic_names.name as generic, sales.discount_type")
            ->selectRaw('SUM(sale_line_batches.quantity_base) as quantity, SUM(sale_lines.discount_amount * sale_line_batches.quantity_base / sale_lines.quantity_base) as discount')
            ->orderBy('supplier')
            ->get();

        foreach ($rows as $row) {
            yield [
                'supplier' => $row->supplier,
                'product' => trim(($row->brand_name ? $row->brand_name.' ('.$row->generic.')' : $row->generic).' '.$row->strength),
                'type' => $row->discount_type === 'senior' ? 'Senior citizen' : 'PWD',
                'quantity' => (int) $row->quantity,
                'discount' => Decimal::round((string) $row->discount),
            ];
        }
    }
}
