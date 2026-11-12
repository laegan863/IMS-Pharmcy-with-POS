<?php

namespace App\Reports;

use App\Enums\Permission;
use App\Models\Sale;

final class DiscountBook extends Report
{
    public function __construct(private string $type) {}

    public function title(): string
    {
        return $this->type === 'senior' ? 'Senior citizen record book' : 'PWD record book';
    }

    public function description(): string
    {
        return 'Every sale with the '.($this->type === 'senior' ? 'senior citizen' : 'PWD').' discount: name, ID number, VAT exemption and discount (FDA Circular 2014-025). Opening it is recorded in the audit log.';
    }

    public function permission(): Permission
    {
        return Permission::ComplianceReports;
    }

    public function group(): string
    {
        return 'Compliance';
    }

    public function containsPersonalData(): bool
    {
        return true;
    }

    public function columns(): array
    {
        return [
            'completed_at' => ['Date', 'datetime'],
            'number' => ['Invoice no.', 'text'],
            'holder' => ['Name', 'text'],
            'id_number' => ['ID no.', 'text'],
            'items' => ['Items', 'text'],
            'gross_amount' => ['Gross', 'money'],
            'vat_exempt_sales' => ['VAT-exempt sales', 'money'],
            'statutory_discount' => ['Discount', 'money'],
            'total' => ['Amount paid', 'money'],
            'status' => ['Status', 'text'],
        ];
    }

    public function rows(ReportFilters $filters): iterable
    {
        $sales = Sale::withoutGlobalScopes()->with('lines')
            ->where('branch_id', $filters->branchId)
            ->where('discount_type', $this->type)
            ->whereBetween('completed_at', [$filters->start(), $filters->end()])
            ->orderBy('id');

        foreach ($sales->lazy() as $sale) {
            yield [
                'completed_at' => $sale->completed_at->toIso8601String(),
                'number' => $sale->number,
                'holder' => $sale->discount_holder_name,
                'id_number' => $sale->discount_id_number,
                'items' => $sale->lines->where('statutory_discount_applied', true)->map(fn ($line) => $line->description.' × '.$line->quantity)->implode('; '),
                ...$sale->only(['gross_amount', 'vat_exempt_sales', 'statutory_discount', 'total', 'status']),
            ];
        }
    }
}
