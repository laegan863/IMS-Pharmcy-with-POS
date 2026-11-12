<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $stock_transfer_id
 * @property int $batch_id
 * @property int $product_id
 * @property int $quantity_base
 * @property-read Batch $batch
 * @property-read Product $product
 */
#[Fillable(['stock_transfer_id', 'batch_id', 'product_id', 'quantity_base'])]
class StockTransferLine extends Model
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
        return ['quantity_base' => 'integer'];
    }
}
