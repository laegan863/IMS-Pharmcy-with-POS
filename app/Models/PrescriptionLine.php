<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $prescription_id
 * @property int $product_id
 * @property int $quantity_prescribed_base
 * @property int $quantity_dispensed_base
 * @property string|null $directions
 * @property-read Prescription $prescription
 * @property-read Product $product
 */
#[Fillable(['prescription_id', 'product_id', 'quantity_prescribed_base', 'quantity_dispensed_base', 'directions'])]
class PrescriptionLine extends Model
{
    /**
     * @return BelongsTo<Prescription, $this>
     */
    public function prescription(): BelongsTo
    {
        return $this->belongsTo(Prescription::class);
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Base units still to be dispensed.
     */
    public function balance(): int
    {
        return max(0, $this->quantity_prescribed_base - $this->quantity_dispensed_base);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['quantity_prescribed_base' => 'integer', 'quantity_dispensed_base' => 'integer'];
    }
}
