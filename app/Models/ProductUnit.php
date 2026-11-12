<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * A unit a product is bought or sold in. conversion_factor is the number of
 * base units in one of this unit: a box of 10 blisters of 10 tablets is 100.
 *
 * Price changes are recorded in product_price_changes automatically.
 *
 * @property int $id
 * @property int $product_id
 * @property string $name
 * @property int $conversion_factor
 * @property bool $is_base
 * @property bool $is_sellable
 * @property bool $is_purchasable
 * @property string|null $price
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Product $product
 */
#[Fillable(['product_id', 'name', 'conversion_factor', 'is_base', 'is_sellable', 'is_purchasable', 'price'])]
class ProductUnit extends Model
{
    use Auditable;

    protected static function booted(): void
    {
        static::saved(function (ProductUnit $unit): void {
            if (! $unit->wasChanged('price') && ! $unit->wasRecentlyCreated) {
                return;
            }

            $old = $unit->wasRecentlyCreated ? null : $unit->getOriginal('price');

            if ($unit->wasRecentlyCreated && $unit->price === null) {
                return;
            }

            $unit->priceChanges()->create([
                'old_price' => $old,
                'new_price' => $unit->price,
                'changed_by' => Auth::id(),
                'changed_at' => now(),
            ]);
        });
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @return HasMany<ProductPriceChange, $this>
     */
    public function priceChanges(): HasMany
    {
        return $this->hasMany(ProductPriceChange::class)->latest('changed_at');
    }

    /**
     * @return HasMany<ProductBarcode, $this>
     */
    public function barcodes(): HasMany
    {
        return $this->hasMany(ProductBarcode::class);
    }

    /**
     * Tables whose rows point at a product unit. A unit used on any document
     * cannot be removed. Each module that adds such a table adds it here.
     *
     * @return list<array{0: string, 1: string}>
     */
    public static function usedBy(): array
    {
        return [];
    }

    /**
     * Whether any document refers to this unit.
     */
    public function isInUse(): bool
    {
        foreach (self::usedBy() as [$table, $column]) {
            if (DB::table($table)->where($column, $this->id)->exists()) {
                return true;
            }
        }

        return false;
    }

    /**
     * Converts a quantity in this unit to base units.
     */
    public function toBase(int $quantity): int
    {
        return $quantity * $this->conversion_factor;
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'conversion_factor' => 'integer',
            'is_base' => 'boolean',
            'is_sellable' => 'boolean',
            'is_purchasable' => 'boolean',
            'price' => 'decimal:2',
        ];
    }
}
