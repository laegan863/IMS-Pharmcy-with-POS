<?php

namespace App\Http\Controllers\Catalog;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductBarcode;
use App\Models\ProductUnit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * JSON product search for the POS and line-item editors. An exact barcode
 * match returns that one product with the scanned unit preselected;
 * otherwise brand, generic name and SKU are searched.
 */
class ProductLookupController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $term = trim((string) $request->query('q', ''));

        if ($term === '') {
            return response()->json(['data' => []]);
        }

        $barcode = ProductBarcode::query()->where('code', $term)->first();

        $query = Product::query()
            ->with(['genericName:id,name', 'dosageForm:id,name', 'units'])
            ->where('is_active', true);

        $products = $barcode
            ? $query->whereKey($barcode->product_id)->get()
            : $query->search($term)->orderBy('brand_name')->limit(20)->get();

        return response()->json([
            'data' => $products->map(fn (Product $product) => [
                'id' => $product->id,
                'sku' => $product->sku,
                'name' => $product->displayName(),
                'generic_name' => $product->genericName->name,
                'classification' => $product->classification->value,
                'tax_class' => $product->tax_class->value,
                'statutory_discount_eligible' => $product->statutory_discount_eligible,
                'scanned_unit_id' => $barcode?->product_unit_id,
                'units' => $product->units->map(fn (ProductUnit $unit) => [
                    'id' => $unit->id,
                    'name' => $unit->name,
                    'conversion_factor' => $unit->conversion_factor,
                    'is_base' => $unit->is_base,
                    'is_sellable' => $unit->is_sellable,
                    'is_purchasable' => $unit->is_purchasable,
                    'price' => $unit->price,
                ])->values(),
            ])->values(),
        ]);
    }
}
