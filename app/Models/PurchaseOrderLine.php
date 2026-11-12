<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $purchase_order_id
 * @property int $product_id
 * @property int $product_unit_id
 * @property int $quantity
 * @property int $quantity_base
 * @property int $received_base
 * @property string $unit_cost
 * @property string $line_total
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read PurchaseOrder $purchaseOrder
 * @property-read Product $product
 * @property-read ProductUnit $unit
 */
#[Fillable(['purchase_order_id', 'product_id', 'product_unit_id', 'quantity', 'quantity_base', 'received_base', 'unit_cost', 'line_total'])]
class PurchaseOrderLine extends Model
{
    /**
     * @return BelongsTo<PurchaseOrder, $this>
     */
    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
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
     * Base units still to be received.
     */
    public function outstandingBase(): int
    {
        return max(0, $this->quantity_base - $this->received_base);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'quantity_base' => 'integer',
            'received_base' => 'integer',
            'unit_cost' => 'decimal:4',
            'line_total' => 'decimal:2',
        ];
    }
}
