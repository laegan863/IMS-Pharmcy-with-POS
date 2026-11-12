<?php

namespace App\Reports;

use App\Enums\Permission;
use App\Support\Decimal;
use Illuminate\Support\Facades\DB;

/**
 * Daily sales: gross, statutory discounts, voids, refunds, net and VAT.
 * Net excludes voided invoices and subtracts refunds made that day.
 */
final class SalesByDay extends Report
{
    public function title(): string
    {
        return 'Sales by day';
    }

    public function description(): string
    {
        return 'Invoices, gross, senior/PWD discounts, voids, refunds, net sales and VAT per day.';
    }

    public function permission(): Permission
    {
        return Permission::ReportsSales;
    }

    public function group(): string
    {
        return 'Sales';
    }

    public function columns(): array
    {
        return [
            'day' => ['Date', 'date'],
            'invoices' => ['Invoices', 'number'],
            'gross' => ['Gross', 'money'],
            'discounts' => ['SC/PWD discounts', 'money'],
            'voids' => ['Voids', 'money'],
            'refunds' => ['Refunds', 'money'],
            'net' => ['Net sales', 'money'],
            'vat' => ['VAT', 'money'],
        ];
    }

    public function rows(ReportFilters $filters): iterable
    {
        $sales = DB::table('sales')
            ->where('branch_id', $filters->branchId)
            ->whereBetween('completed_at', [$filters->start(), $filters->end()])
            ->selectRaw('DATE(completed_at) as day, COUNT(*) as invoices, SUM(gross_amount) as gross, SUM(statutory_discount) as discounts')
            ->selectRaw("SUM(CASE WHEN status <> 'voided' THEN total ELSE 0 END) as kept, SUM(CASE WHEN status <> 'voided' THEN vat_amount ELSE 0 END) as vat")
            ->groupByRaw('DATE(completed_at)')
            ->get()
            ->keyBy('day');

        $adjustments = DB::table('sale_adjustments')
            ->where('branch_id', $filters->branchId)
            ->whereBetween('created_at', [$filters->start(), $filters->end()])
            ->selectRaw("DATE(created_at) as day, SUM(CASE WHEN type = 'void' THEN amount ELSE 0 END) as voids, SUM(CASE WHEN type = 'refund' THEN amount ELSE 0 END) as refunds, SUM(CASE WHEN type = 'refund' THEN vat_amount ELSE 0 END) as refund_vat")
            ->groupByRaw('DATE(created_at)')
            ->get()
            ->keyBy('day');

        $days = $sales->keys()->merge($adjustments->keys())->unique()->sort()->values();

        foreach ($days as $day) {
            $sale = $sales->get($day);
            $adjustment = $adjustments->get($day);

            yield [
                'day' => (string) $day,
                'invoices' => (int) ($sale->invoices ?? 0),
                'gross' => Decimal::round((string) ($sale->gross ?? '0')),
                'discounts' => Decimal::round((string) ($sale->discounts ?? '0')),
                'voids' => Decimal::round((string) ($adjustment->voids ?? '0')),
                'refunds' => Decimal::round((string) ($adjustment->refunds ?? '0')),
                'net' => Decimal::sub((string) ($sale->kept ?? '0'), (string) ($adjustment->refunds ?? '0')),
                'vat' => Decimal::sub((string) ($sale->vat ?? '0'), (string) ($adjustment->refund_vat ?? '0')),
            ];
        }
    }
}
