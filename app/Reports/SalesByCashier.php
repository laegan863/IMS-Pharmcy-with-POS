<?php

namespace App\Reports;

use App\Enums\Permission;
use App\Support\Decimal;
use Illuminate\Support\Facades\DB;

final class SalesByCashier extends Report
{
    public function title(): string
    {
        return 'Sales by cashier';
    }

    public function description(): string
    {
        return 'Invoices, net sales, voids and cash variance per cashier.';
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
            'cashier' => ['Cashier', 'text'],
            'invoices' => ['Invoices', 'number'],
            'net' => ['Net sales', 'money'],
            'voided' => ['Voided invoices', 'number'],
            'cash_variance' => ['Cash over/short', 'money'],
        ];
    }

    public function rows(ReportFilters $filters): iterable
    {
        $sales = DB::table('sales')
            ->join('users', 'users.id', '=', 'sales.user_id')
            ->where('sales.branch_id', $filters->branchId)
            ->whereBetween('sales.completed_at', [$filters->start(), $filters->end()])
            ->groupBy('users.id', 'users.name')
            ->selectRaw("users.id, users.name, COUNT(*) as invoices, SUM(CASE WHEN sales.status <> 'voided' THEN sales.total ELSE 0 END) as net, SUM(CASE WHEN sales.status = 'voided' THEN 1 ELSE 0 END) as voided")
            ->orderByDesc('net')
            ->get();

        $variance = DB::table('shifts')
            ->where('branch_id', $filters->branchId)
            ->whereBetween('closed_at', [$filters->start(), $filters->end()])
            ->groupBy('user_id')
            ->pluck(DB::raw('SUM(variance)'), 'user_id');

        foreach ($sales as $row) {
            yield [
                'cashier' => $row->name,
                'invoices' => (int) $row->invoices,
                'net' => Decimal::round((string) $row->net),
                'voided' => (int) $row->voided,
                'cash_variance' => Decimal::round((string) ($variance[$row->id] ?? '0')),
            ];
        }
    }
}
