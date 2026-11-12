<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * How many base units of one batch a sale line took, for recall tracing and
 * for returning voided or refunded stock to the same batch.
 *
 * @property int $id
 * @property int $sale_line_id
 * @property int $batch_id
 * @property int $quantity_base
 * @property string $unit_cost
 * @property int $returned_base
 * @property-read Batch $batch
 * @property-read SaleLine $saleLine
 */
#[Fillable(['sale_line_id', 'batch_id', 'quantity_base', 'unit_cost', 'returned_base'])]
class SaleLineBatch extends Model
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
     * @return BelongsTo<SaleLine, $this>
     */
    public function saleLine(): BelongsTo
    {
        return $this->belongsTo(SaleLine::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['quantity_base' => 'integer', 'returned_base' => 'integer', 'unit_cost' => 'decimal:4'];
    }
}
