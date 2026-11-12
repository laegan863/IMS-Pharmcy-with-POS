<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $supplier_return_id
 * @property int $batch_id
 * @property int $product_id
 * @property int $quantity_base
 * @property string $unit_cost
 * @property string $line_total
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Batch $batch
 * @property-read Product $product
 */
#[Fillable(['supplier_return_id', 'batch_id', 'product_id', 'quantity_base', 'unit_cost', 'line_total'])]
class SupplierReturnLine extends Model
{
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
        return [
            'quantity_base' => 'integer',
            'unit_cost' => 'decimal:4',
            'line_total' => 'decimal:2',
        ];
    }
}
