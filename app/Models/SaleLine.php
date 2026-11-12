<?php

namespace App\Models;

use App\Enums\TaxClass;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $sale_id
 * @property int $product_id
 * @property int $product_unit_id
 * @property string $description
 * @property int $quantity
 * @property int $quantity_base
 * @property string $unit_price
 * @property string $list_price
 * @property int|null $price_override_by
 * @property TaxClass $tax_class
 * @property bool $statutory_discount_applied
 * @property string $gross_amount
 * @property string $vatable_amount
 * @property string $vat_amount
 * @property string $vat_exempt_amount
 * @property string $zero_rated_amount
 * @property string $discount_amount
 * @property string $line_total
 * @property string $cost_total
 * @property int $refunded_base
 * @property-read Sale $sale
 * @property-read Product $product
 * @property-read ProductUnit $unit
 * @property-read Collection<int, SaleLineBatch> $batches
 */
#[Fillable([
    'sale_id', 'product_id', 'product_unit_id', 'description', 'quantity', 'quantity_base', 'unit_price', 'list_price',
    'price_override_by', 'tax_class', 'statutory_discount_applied', 'gross_amount', 'vatable_amount', 'vat_amount',
    'vat_exempt_amount', 'zero_rated_amount', 'discount_amount', 'line_total', 'cost_total', 'refunded_base',
])]
class SaleLine extends Model
{
    /**
     * @return BelongsTo<Sale, $this>
     */
    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @return BelongsTo<ProductUnit, $this>
     */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(ProductUnit::class, 'product_unit_id');
    }

    /**
     * Which batches the sold units came from.
     *
     * @return HasMany<SaleLineBatch, $this>
     */
    public function batches(): HasMany
    {
        return $this->hasMany(SaleLineBatch::class);
    }

    /**
     * Base units not yet refunded.
     */
    public function refundableBase(): int
    {
        return $this->quantity_base - $this->refunded_base;
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tax_class' => TaxClass::class,
            'statutory_discount_applied' => 'boolean',
            'quantity' => 'integer',
            'quantity_base' => 'integer',
            'refunded_base' => 'integer',
            'unit_price' => 'decimal:2',
            'list_price' => 'decimal:2',
            'gross_amount' => 'decimal:2',
            'vatable_amount' => 'decimal:2',
            'vat_amount' => 'decimal:2',
            'vat_exempt_amount' => 'decimal:2',
            'zero_rated_amount' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'line_total' => 'decimal:2',
            'cost_total' => 'decimal:4',
        ];
    }
}
