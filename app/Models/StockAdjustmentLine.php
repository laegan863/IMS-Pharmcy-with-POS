<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $stock_adjustment_id
 * @property int $batch_id
 * @property int $product_id
 * @property int $quantity_change
 * @property-read Batch $batch
 * @property-read Product $product
 */
#[Fillable(['stock_adjustment_id', 'batch_id', 'product_id', 'quantity_change'])]
class StockAdjustmentLine extends Model
{
    public $timestamps = false;

    /**
     * @return BelongsTo<Batch, $this>
     */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class);
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['quantity_change' => 'integer'];
    }
}
