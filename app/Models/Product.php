<?php

namespace App\Models;

use App\Enums\ProductClassification;
use App\Enums\StorageCondition;
use App\Enums\TaxClass;
use App\Models\Concerns\Auditable;
use Carbon\CarbonImmutable;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * A catalog item. Stock is counted in the base unit; see ProductUnit.
 *
 * @property int $id
 * @property string $sku
 * @property int $generic_name_id
 * @property string|null $brand_name
 * @property string|null $strength
 * @property int|null $dosage_form_id
 * @property int|null $manufacturer_id
 * @property int|null $category_id
 * @property ProductClassification $classification
 * @property string|null $fda_registration_number
 * @property TaxClass $tax_class
 * @property CarbonImmutable|null $tax_class_effective_from
 * @property bool $statutory_discount_eligible
 * @property StorageCondition $storage_condition
 * @property int $reorder_point
 * @property int|null $max_level
 * @property string $average_cost Moving average cost per base unit, set only by StockService.
 * @property string|null $description
 * @property bool $is_active
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read GenericName $genericName
 * @property-read DosageForm|null $dosageForm
 * @property-read Manufacturer|null $manufacturer
 * @property-read Category|null $category
 * @property-read Collection<int, ProductUnit> $units
 * @property-read ProductUnit|null $baseUnit
 * @property-read Collection<int, ProductBarcode> $barcodes
 */
#[Fillable([
    'sku', 'generic_name_id', 'brand_name', 'strength', 'dosage_form_id', 'manufacturer_id', 'category_id',
    'classification', 'fda_registration_number', 'tax_class', 'tax_class_effective_from',
    'statutory_discount_eligible', 'storage_condition', 'reorder_point', 'max_level', 'description', 'is_active',
])]
class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use Auditable, HasFactory;

    /**
     * @return BelongsTo<GenericName, $this>
     */
    public function genericName(): BelongsTo
    {
        return $this->belongsTo(GenericName::class);
    }

    /**
     * @return BelongsTo<DosageForm, $this>
     */
    public function dosageForm(): BelongsTo
    {
        return $this->belongsTo(DosageForm::class);
    }

    /**
     * @return BelongsTo<Manufacturer, $this>
     */
    public function manufacturer(): BelongsTo
    {
        return $this->belongsTo(Manufacturer::class);
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * @return HasMany<ProductUnit, $this>
     */
    public function units(): HasMany
    {
        return $this->hasMany(ProductUnit::class)->orderBy('conversion_factor');
    }

    /**
     * @return HasOne<ProductUnit, $this>
     */
    public function baseUnit(): HasOne
    {
        return $this->hasOne(ProductUnit::class)->where('is_base', true);
    }

    /**
     * @return HasMany<ProductBarcode, $this>
     */
    public function barcodes(): HasMany
    {
        return $this->hasMany(ProductBarcode::class);
    }

    /**
     * Matches brand name, generic name, SKU or an exact barcode.
     *
     * @param  Builder<Product>  $query
     */
    #[Scope]
    protected function search(Builder $query, string $term): void
    {
        $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $term).'%';

        $query->where(fn (Builder $query) => $query
            ->where('products.brand_name', 'like', $like)
            ->orWhere('products.sku', $term)
            ->orWhereHas('genericName', fn (Builder $query) => $query->where('name', 'like', $like))
            ->orWhereHas('barcodes', fn (Builder $query) => $query->where('code', $term)));
    }

    /**
     * The name shown on screens and receipts, e.g. "Biogesic (Paracetamol) 500 mg Tablet".
     */
    public function displayName(): string
    {
        $generic = $this->genericName->name;
        $name = $this->brand_name ? "{$this->brand_name} ({$generic})" : $generic;

        return trim(implode(' ', array_filter([$name, $this->strength, $this->dosageForm?->name])));
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'classification' => ProductClassification::class,
            'tax_class' => TaxClass::class,
            'storage_condition' => StorageCondition::class,
            'tax_class_effective_from' => 'date',
            'statutory_discount_eligible' => 'boolean',
            'reorder_point' => 'integer',
            'max_level' => 'integer',
            'average_cost' => 'decimal:4',
            'is_active' => 'boolean',
        ];
    }
}
