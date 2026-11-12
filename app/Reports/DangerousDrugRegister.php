<?php

namespace App\Reports;

use App\Enums\Permission;
use App\Enums\ProductClassification;
use App\Enums\StockMovementType;
use App\Models\DangerousDrugEntry;
use App\Models\Product;
use App\Models\Sale;
use App\Models\StockMovement;

final class DangerousDrugRegister extends Report
{
    public function title(): string
    {
        return 'Dangerous drug register';
    }

    public function description(): string
    {
        return 'Every receipt and dispensing of S2 drugs with a running balance per product, patient, prescriber and S2 number, form serial and pharmacist.';
    }

    public function permission(): Permission
    {
        return Permission::DangerousDrugsDispense;
    }

    public function group(): string
    {
        return 'Compliance';
    }

    public function containsPersonalData(): bool
    {
        return true;
    }

    public function totalColumns(): array
    {
        return [];
    }

    public function columns(): array
    {
        return [
            'date' => ['Date', 'datetime'],
            'product' => ['Drug', 'text'],
            'reference' => ['Reference', 'text'],
            'in' => ['In', 'number'],
            'out' => ['Out', 'number'],
            'balance' => ['Balance', 'number'],
            'patient' => ['Patient', 'text'],
            'address' => ['Address', 'text'],
            'prescriber' => ['Prescriber (S2)', 'text'],
            'form_serial' => ['Form serial', 'text'],
            'pharmacist' => ['Pharmacist', 'text'],
        ];
    }

    public function rows(ReportFilters $filters): iterable
    {
        $products = Product::query()->with(['genericName', 'dosageForm'])->where('classification', ProductClassification::DangerousDrug->value)->get();

        foreach ($products as $product) {
            // Balance before the period, then every movement in it.
            $balance = (int) StockMovement::withoutGlobalScopes()
                ->where('branch_id', $filters->branchId)
                ->where('product_id', $product->id)
                ->where('created_at', '<', $filters->start())
                ->sum('quantity');

            $entries = DangerousDrugEntry::withoutGlobalScopes()->with(['saleLine', 'pharmacist'])
                ->where('branch_id', $filters->branchId)->where('product_id', $product->id)->get()->keyBy('sale_line_id');

            $movements = StockMovement::withoutGlobalScopes()
                ->where('branch_id', $filters->branchId)
                ->where('product_id', $product->id)
                ->whereBetween('created_at', [$filters->start(), $filters->end()])
                ->orderBy('id')
                ->get();

            foreach ($movements as $movement) {
                $balance += $movement->quantity;
                $entry = null;

                if ($movement->type === StockMovementType::Sale && $movement->source_type === (new Sale)->getMorphClass()) {
                    $entry = $entries->first(fn (DangerousDrugEntry $candidate) => $candidate->saleLine->sale_id === $movement->source_id);
                }

                yield [
                    'date' => $movement->created_at->toIso8601String(),
                    'product' => $product->displayName(),
                    'reference' => $movement->reference ?? $movement->type->label(),
                    'in' => $movement->quantity > 0 ? $movement->quantity : null,
                    'out' => $movement->quantity < 0 ? -$movement->quantity : null,
                    'balance' => $balance,
                    'patient' => $entry?->patient_name,
                    'address' => $entry?->patient_address,
                    'prescriber' => $entry ? $entry->prescriber_name.' (S2 '.$entry->prescriber_s2_number.')' : null,
                    'form_serial' => $entry?->special_form_serial,
                    'pharmacist' => $entry?->pharmacist->name,
                ];
            }
        }
    }
}
