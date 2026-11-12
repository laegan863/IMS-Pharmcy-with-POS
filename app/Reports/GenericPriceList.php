<?php

namespace App\Reports;

use App\Enums\Permission;
use App\Enums\ProductClassification;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Support\Decimal;

final class GenericPriceList extends Report
{
    public function title(): string
    {
        return 'Generic price list';
    }

    public function description(): string
    {
        return 'Medicines grouped by generic name with prices, for the generic menu card the Generics Act requires on display.';
    }

    public function permission(): Permission
    {
        return Permission::ProductsView;
    }

    public function group(): string
    {
        return 'Compliance';
    }

    public function usesDates(): bool
    {
        return false;
    }

    public function totalColumns(): array
    {
        return [];
    }

    public function columns(): array
    {
        return [
            'generic' => ['Generic name', 'text'],
            'product' => ['Brand / product', 'text'],
            'unit' => ['Unit', 'text'],
            'price' => ['Price', 'money'],
        ];
    }

    public function rows(ReportFilters $filters): iterable
    {
        $products = Product::query()
            ->with(['genericName', 'dosageForm', 'units'])
            ->where('is_active', true)
            ->where('classification', '<>', ProductClassification::NonDrug->value)
            ->get()
            ->sortBy(fn (Product $product) => mb_strtolower($product->genericName->name).' '.$product->strength);

        foreach ($products as $product) {
            foreach ($product->units->where('is_sellable', true)->whereNotNull('price') as $unit) {
                /** @var ProductUnit $unit */
                yield [
                    'generic' => $product->genericName->name.($product->strength ? ' '.$product->strength : ''),
                    'product' => $product->brand_name ?? 'Generic',
                    'unit' => $unit->name,
                    'price' => Decimal::round((string) $unit->price),
                ];
            }
        }
    }
}
