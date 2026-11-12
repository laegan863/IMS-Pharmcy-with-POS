<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $sale_adjustment_id
 * @property int $sale_line_id
 * @property int $quantity_base
 * @property string $amount
 * @property-read SaleLine $saleLine
 */
#[Fillable(['sale_adjustment_id', 'sale_line_id', 'quantity_base', 'amount'])]
class SaleAdjustmentLine extends Model
{
    public $timestamps = false;

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
        return ['quantity_base' => 'integer', 'amount' => 'decimal:2'];
    }
}
