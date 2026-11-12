<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $goods_receipt_id
 * @property int|null $purchase_order_line_id
 * @property int $product_id
 * @property int $product_unit_id
 * @property string $lot_number
 * @property CarbonImmutable $expires_on
 * @property int $quantity
 * @property int $free_quantity
 * @property string $unit_cost
 * @property string $line_total
 * @property int|null $batch_id
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read GoodsReceipt $goodsReceipt
 * @property-read Product $product
 * @property-read ProductUnit $unit
 * @property-read PurchaseOrderLine|null $purchaseOrderLine
 */
#[Fillable(['goods_receipt_id', 'purchase_order_line_id', 'product_id', 'product_unit_id', 'lot_number', 'expires_on', 'quantity', 'free_quantity', 'unit_cost', 'line_total', 'batch_id'])]
class GoodsReceiptLine extends Model
{
    /**
     * @return BelongsTo<GoodsReceipt, $this>
     */
    public function goodsReceipt(): BelongsTo
    {
        return $this->belongsTo(GoodsReceipt::class);
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
     * @return BelongsTo<PurchaseOrderLine, $this>
     */
    public function purchaseOrderLine(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrderLine::class);
    }

    /**
     * Paid plus free quantity, in base units.
     */
    public function totalBase(): int
    {
        return ($this->quantity + $this->free_quantity) * $this->unit->conversion_factor;
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expires_on' => 'date',
            'quantity' => 'integer',
            'free_quantity' => 'integer',
            'unit_cost' => 'decimal:4',
            'line_total' => 'decimal:2',
        ];
    }
}
