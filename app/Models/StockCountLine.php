<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $stock_count_id
 * @property int $batch_id
 * @property int $product_id
 * @property int $system_quantity
 * @property int|null $counted_quantity
 * @property-read Batch $batch
 * @property-read Product $product
 */
#[Fillable(['stock_count_id', 'batch_id', 'product_id', 'system_quantity', 'counted_quantity'])]
class StockCountLine extends Model
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
     * Counted minus system; null until counted.
     */
    public function variance(): ?int
    {
        return $this->counted_quantity === null ? null : $this->counted_quantity - $this->system_quantity;
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['system_quantity' => 'integer', 'counted_quantity' => 'integer'];
    }
}
