<?php

namespace App\Reports;

use App\Enums\Permission;
use App\Models\Scopes\BranchScope;
use App\Models\StockMovement;
use App\Support\Decimal;

final class StockMovementsReport extends Report
{
    public function title(): string
    {
        return 'Stock movements';
    }

    public function description(): string
    {
        return 'Every receipt, sale, adjustment, transfer and disposal in the period, with batch and value.';
    }

    public function permission(): Permission
    {
        return Permission::ReportsInventory;
    }

    public function group(): string
    {
        return 'Inventory';
    }

    public function totalColumns(): array
    {
        return [];
    }

    public function columns(): array
    {
        return [
            'date' => ['Date', 'datetime'],
            'type' => ['Type', 'text'],
            'reference' => ['Reference', 'text'],
            'product' => ['Product', 'text'],
            'lot' => ['Lot', 'text'],
            'quantity' => ['Quantity', 'number'],
            'value' => ['Value at cost', 'money'],
            'user' => ['By', 'text'],
        ];
    }

    public function rows(ReportFilters $filters): iterable
    {
        $movements = StockMovement::withoutGlobalScope(BranchScope::class)
            ->with(['product.genericName', 'product.dosageForm', 'batch', 'user'])
            ->where('branch_id', $filters->branchId)
            ->whereBetween('created_at', [$filters->start(), $filters->end()])
            ->orderBy('id');

        foreach ($movements->lazy(500) as $movement) {
            yield [
                'date' => $movement->created_at->toIso8601String(),
                'type' => $movement->type->label(),
                'reference' => $movement->reference,
                'product' => $movement->product->displayName(),
                'lot' => $movement->batch->lot_number,
                'quantity' => $movement->quantity,
                'value' => Decimal::mul($movement->quantity, $movement->unit_cost),
                'user' => $movement->user?->name,
            ];
        }
    }
}
