<?php

namespace App\Reports;

use App\Enums\Permission;
use App\Models\SaleAdjustment;

final class VoidsAndRefunds extends Report
{
    public function title(): string
    {
        return 'Voids and refunds';
    }

    public function description(): string
    {
        return 'Each void and refund with its own number, the original invoice, reason and approving supervisor.';
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
            'created_at' => ['Date', 'datetime'],
            'number' => ['Number', 'text'],
            'type' => ['Type', 'text'],
            'invoice' => ['Original invoice', 'text'],
            'reason' => ['Reason', 'text'],
            'user' => ['Processed by', 'text'],
            'approver' => ['Approved by', 'text'],
            'vat_amount' => ['VAT', 'money'],
            'amount' => ['Amount', 'money'],
        ];
    }

    public function rows(ReportFilters $filters): iterable
    {
        foreach (SaleAdjustment::withoutGlobalScopes()->with(['sale', 'user', 'approver'])
            ->where('branch_id', $filters->branchId)
            ->whereBetween('created_at', [$filters->start(), $filters->end()])->orderBy('id')->get() as $adjustment) {
            yield [
                'created_at' => $adjustment->created_at->toIso8601String(),
                'number' => $adjustment->number,
                'type' => $adjustment->type,
                'invoice' => $adjustment->sale->number,
                'reason' => $adjustment->reason,
                'user' => $adjustment->user->name,
                'approver' => $adjustment->approver->name,
                'vat_amount' => $adjustment->vat_amount,
                'amount' => $adjustment->amount,
            ];
        }
    }
}
