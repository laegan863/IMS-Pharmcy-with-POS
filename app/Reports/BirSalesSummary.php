<?php

namespace App\Reports;

use App\Enums\Permission;
use App\Models\Reading;

final class BirSalesSummary extends Report
{
    public function title(): string
    {
        return 'BIR sales summary';
    }

    public function description(): string
    {
        return 'One row per Z-reading: invoice range, sales, VAT breakdown, discounts, voids, refunds and the grand total.';
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
            'period_end' => ['Date', 'date'],
            'terminal' => ['Terminal', 'text'],
            'z_counter' => ['Z no.', 'number'],
            'first_invoice' => ['Beginning SI', 'text'],
            'last_invoice' => ['Ending SI', 'text'],
            'grand_total_before' => ['Beginning grand total', 'money'],
            'grand_total_after' => ['Ending grand total', 'money'],
            'gross_sales' => ['Gross sales', 'money'],
            'vatable_sales' => ['VATable', 'money'],
            'vat_amount' => ['VAT', 'money'],
            'vat_exempt_sales' => ['VAT-exempt', 'money'],
            'zero_rated_sales' => ['Zero-rated', 'money'],
            'senior_discounts' => ['Senior disc.', 'money'],
            'pwd_discounts' => ['PWD disc.', 'money'],
            'voids' => ['Voids', 'money'],
            'refunds' => ['Refunds', 'money'],
            'net_after_adjustments' => ['Net sales', 'money'],
        ];
    }

    public function totalColumns(): array
    {
        return array_values(array_diff(parent::totalColumns(), ['grand_total_before', 'grand_total_after']));
    }

    public function rows(ReportFilters $filters): iterable
    {
        foreach (Reading::withoutGlobalScopes()->with('terminal')->where('branch_id', $filters->branchId)
            ->whereBetween('period_end', [$filters->start(), $filters->end()])->orderBy('id')->get() as $reading) {
            $data = $reading->data;

            yield [
                'period_end' => $reading->period_end->toDateString(),
                'terminal' => $reading->terminal->code,
                'z_counter' => $reading->z_counter,
                'first_invoice' => $data['first_invoice'] ?? null,
                'last_invoice' => $data['last_invoice'] ?? null,
                'grand_total_before' => $reading->grand_total_before,
                'grand_total_after' => $reading->grand_total_after,
                ...array_intersect_key($data, array_flip(['gross_sales', 'vatable_sales', 'vat_amount', 'vat_exempt_sales', 'zero_rated_sales', 'senior_discounts', 'pwd_discounts', 'voids', 'refunds', 'net_after_adjustments'])),
            ];
        }
    }
}
