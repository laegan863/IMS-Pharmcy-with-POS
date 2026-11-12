<?php

namespace App\Reports;

use App\Enums\Permission;
use App\Models\PrescriptionLine;

final class PrescriptionBook extends Report
{
    public function title(): string
    {
        return 'Prescription book';
    }

    public function description(): string
    {
        return 'Prescriptions received, with quantity prescribed, dispensed and balance per line.';
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
            'prescribed_on' => ['Rx date', 'date'],
            'number' => ['Rx no.', 'text'],
            'patient' => ['Patient', 'text'],
            'prescriber' => ['Prescriber', 'text'],
            'prc' => ['PRC no.', 'text'],
            'product' => ['Medicine', 'text'],
            'prescribed' => ['Prescribed', 'number'],
            'dispensed' => ['Dispensed', 'number'],
            'balance' => ['Balance', 'number'],
        ];
    }

    public function rows(ReportFilters $filters): iterable
    {
        foreach (PrescriptionLine::query()->with(['prescription.prescriber', 'product.genericName', 'product.dosageForm'])
            ->whereHas('prescription', fn ($query) => $query->where('branch_id', $filters->branchId)
                ->whereBetween('created_at', [$filters->start(), $filters->end()]))
            ->orderBy('prescription_id')->get() as $line) {
            yield [
                'prescribed_on' => $line->prescription->prescribed_on->toDateString(),
                'number' => $line->prescription->number,
                'patient' => $line->prescription->patient_name,
                'prescriber' => $line->prescription->prescriber->name,
                'prc' => $line->prescription->prescriber->prc_license_number,
                'product' => $line->product->displayName(),
                'prescribed' => $line->quantity_prescribed_base,
                'dispensed' => $line->quantity_dispensed_base,
                'balance' => $line->balance(),
            ];
        }
    }
}
