<?php

namespace App\Reports;

use App\Enums\Permission;
use App\Models\Sale;

final class SalesJournal extends Report
{
    public function title(): string
    {
        return 'Sales journal';
    }

    public function description(): string
    {
        return 'Every invoice with its VAT breakdown, in number order (BIR e-journal / back-end report).';
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
            'completed_at' => ['Date', 'datetime'],
            'number' => ['Invoice no.', 'text'],
            'terminal' => ['Terminal', 'text'],
            'status' => ['Status', 'text'],
            'gross_amount' => ['Gross', 'money'],
            'vatable_sales' => ['VATable', 'money'],
            'vat_amount' => ['VAT', 'money'],
            'vat_exempt_sales' => ['VAT-exempt', 'money'],
            'zero_rated_sales' => ['Zero-rated', 'money'],
            'statutory_discount' => ['SC/PWD discount', 'money'],
            'total' => ['Net', 'money'],
        ];
    }

    public function rows(ReportFilters $filters): iterable
    {
        foreach (Sale::withoutGlobalScopes()->with('terminal')->where('branch_id', $filters->branchId)
            ->whereBetween('completed_at', [$filters->start(), $filters->end()])->orderBy('id')->lazy() as $sale) {
            yield [
                'completed_at' => $sale->completed_at->toIso8601String(),
                'number' => $sale->number,
                'terminal' => $sale->terminal->code,
                'status' => $sale->status,
                ...$sale->only(['gross_amount', 'vatable_sales', 'vat_amount', 'vat_exempt_sales', 'zero_rated_sales', 'statutory_discount', 'total']),
            ];
        }
    }
}
