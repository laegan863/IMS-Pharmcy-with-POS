<?php

namespace App\Reports;

use App\Enums\Permission;
use App\Services\Purchasing\ReorderSuggestions;

final class ReorderReport extends Report
{
    public function title(): string
    {
        return 'Reorder list';
    }

    public function description(): string
    {
        return 'Products at or below their reorder point with a suggested order. Create the order from Purchasing → Reorder list.';
    }

    public function permission(): Permission
    {
        return Permission::ReportsInventory;
    }

    public function group(): string
    {
        return 'Inventory';
    }

    public function usesDates(): bool
    {
        return false;
    }

    public function columns(): array
    {
        return [
            'sku' => ['SKU', 'text'],
            'name' => ['Product', 'text'],
            'on_hand' => ['On hand', 'number'],
            'on_order' => ['On order', 'number'],
            'reorder_point' => ['Reorder at', 'number'],
            'sold_30_days' => ['Sold 30 days', 'number'],
            'suggested' => ['Suggested order', 'text'],
            'unit_cost' => ['Last cost', 'money'],
        ];
    }

    public function totalColumns(): array
    {
        return [];
    }

    public function rows(ReportFilters $filters): iterable
    {
        foreach (app(ReorderSuggestions::class)->forBranch($filters->branchId) as $row) {
            yield [...$row, 'suggested' => $row['quantity'].' '.$row['unit']];
        }
    }
}
