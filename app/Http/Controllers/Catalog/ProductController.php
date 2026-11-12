<?php

namespace App\Http\Controllers\Catalog;

use App\Enums\Permission;
use App\Enums\ProductClassification;
use App\Enums\StorageCondition;
use App\Enums\TaxClass;
use App\Http\Controllers\Controller;
use App\Http\Requests\Catalog\ProductRequest;
use App\Models\Category;
use App\Models\DosageForm;
use App\Models\GenericName;
use App\Models\Manufacturer;
use App\Models\Product;
use App\Models\ProductPriceChange;
use App\Models\ProductUnit;
use App\Services\Catalog\ProductService;
use App\Support\TableQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProductController extends Controller
{
    public function index(Request $request): Response
    {
        $table = TableQuery::for(Product::query()->with(['genericName:id,name', 'dosageForm:id,name', 'category:id,name', 'units']), $request)
            ->searchUsing(fn (Builder $query, string $term) => $query->search($term))
            ->sortable([
                'sku' => 'products.sku',
                'brand_name' => 'products.brand_name',
                'generic_name' => fn (Builder $query, string $direction) => $query->orderBy(
                    GenericName::select('name')->whereColumn('generic_names.id', 'products.generic_name_id'),
                    $direction === 'desc' ? 'desc' : 'asc',
                ),
            ])
            ->defaultSort('generic_name')
            ->filter('classification', fn (Builder $query, string $value) => $query->where('classification', $value))
            ->filter('category', fn (Builder $query, string $value) => $query->where('category_id', (int) $value))
            ->filter('status', fn (Builder $query, string $value) => $query->where('is_active', $value === 'active'))
            ->paginate(fn (Product $product) => [
                'id' => $product->id,
                'sku' => $product->sku,
                'name' => $product->displayName(),
                'generic_name' => $product->genericName->name,
                'brand_name' => $product->brand_name,
                'category' => $product->category?->name,
                'classification' => $product->classification->value,
                'classification_label' => $product->classification->label(),
                'units' => $product->units->map(fn (ProductUnit $unit) => [
                    'name' => $unit->name,
                    'conversion_factor' => $unit->conversion_factor,
                    'price' => $unit->price,
                ])->values(),
                'is_active' => $product->is_active,
            ]);

        return Inertia::render('catalog/products/index', [
            ...$table,
            'classifications' => ProductClassification::options(),
            'categories' => Category::options(),
            'canManage' => $request->user()?->can(Permission::ProductsManage->value) ?? false,
            'canImport' => $request->user()?->can(Permission::ProductsImport->value) ?? false,
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('catalog/products/form', [
            'product' => null,
            ...$this->formOptions($request),
        ]);
    }

    public function store(ProductRequest $request, ProductService $products): RedirectResponse
    {
        $product = $products->save(null, $request->productAttributes(), $request->units(), $request->barcodes(), $this->canSetPrices($request));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Product created.')]);

        return to_route('catalog.products.edit', $product);
    }

    public function edit(Request $request, Product $product): Response
    {
        $product->load(['genericName', 'manufacturer', 'units', 'barcodes.unit']);

        $priceHistory = ProductPriceChange::query()
            ->whereIn('product_unit_id', $product->units->modelKeys())
            ->with(['changer:id,name'])
            ->latest('changed_at')
            ->limit(30)
            ->get()
            ->map(fn (ProductPriceChange $change) => [
                'id' => $change->id,
                'unit' => $product->units->firstWhere('id', $change->product_unit_id)?->name,
                'old_price' => $change->old_price,
                'new_price' => $change->new_price,
                'changed_by' => $change->changer?->name,
                'changed_at' => $change->changed_at->toIso8601String(),
            ]);

        return Inertia::render('catalog/products/form', [
            'product' => [
                'id' => $product->id,
                'sku' => $product->sku,
                'generic_name' => $product->genericName->name,
                'brand_name' => $product->brand_name,
                'strength' => $product->strength,
                'dosage_form_id' => $product->dosage_form_id,
                'manufacturer' => $product->manufacturer?->name,
                'category_id' => $product->category_id,
                'classification' => $product->classification->value,
                'fda_registration_number' => $product->fda_registration_number,
                'tax_class' => $product->tax_class->value,
                'tax_class_effective_from' => $product->tax_class_effective_from?->toDateString(),
                'statutory_discount_eligible' => $product->statutory_discount_eligible,
                'storage_condition' => $product->storage_condition->value,
                'reorder_point' => $product->reorder_point,
                'max_level' => $product->max_level,
                'description' => $product->description,
                'is_active' => $product->is_active,
                'units' => $product->units->map(fn (ProductUnit $unit) => $unit->only([
                    'id', 'name', 'conversion_factor', 'is_base', 'is_sellable', 'is_purchasable', 'price',
                ]))->values(),
                'barcodes' => $product->barcodes->map(fn ($barcode) => [
                    'code' => $barcode->code,
                    'unit' => $barcode->unit->name,
                ])->values(),
            ],
            'priceHistory' => $priceHistory,
            ...$this->formOptions($request),
        ]);
    }

    public function update(ProductRequest $request, Product $product, ProductService $products): RedirectResponse
    {
        $products->save($product, $request->productAttributes(), $request->units(), $request->barcodes(), $this->canSetPrices($request));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Product saved.')]);

        return to_route('catalog.products.edit', $product);
    }

    private function canSetPrices(Request $request): bool
    {
        return $request->user()?->can(Permission::PricesManage->value) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    private function formOptions(Request $request): array
    {
        return [
            'genericNames' => GenericName::query()->where('is_active', true)->orderBy('name')->pluck('name'),
            'manufacturers' => Manufacturer::query()->where('is_active', true)->orderBy('name')->pluck('name'),
            'dosageForms' => DosageForm::options(),
            'categories' => Category::options(),
            'classifications' => ProductClassification::options(),
            'taxClasses' => TaxClass::options(),
            'storageConditions' => StorageCondition::options(),
            'canSetPrices' => $this->canSetPrices($request),
        ];
    }
}
