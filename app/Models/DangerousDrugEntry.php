<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * One line of the dangerous drug register. Never changed or deleted.
 *
 * @property int $id
 * @property int $branch_id
 * @property int $sale_line_id
 * @property int $product_id
 * @property int $prescription_id
 * @property string $special_form_serial
 * @property string $patient_name
 * @property string|null $patient_address
 * @property string $prescriber_name
 * @property string $prescriber_s2_number
 * @property int $quantity_base
 * @property int $balance_after
 * @property int $pharmacist_id
 * @property CarbonImmutable $created_at
 * @property-read Product $product
 * @property-read User $pharmacist
 * @property-read SaleLine $saleLine
 */
#[Fillable(['branch_id', 'sale_line_id', 'product_id', 'prescription_id', 'special_form_serial', 'patient_name', 'patient_address', 'prescriber_name', 'prescriber_s2_number', 'quantity_base', 'balance_after', 'pharmacist_id', 'created_at'])]
class DangerousDrugEntry extends Model
{
    use BelongsToBranch;

    public const UPDATED_AT = null;

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Register entries cannot be changed.'));
        static::deleting(fn () => throw new LogicException('Register entries cannot be deleted.'));
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function pharmacist(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pharmacist_id');
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
        return ['quantity_base' => 'integer', 'balance_after' => 'integer', 'created_at' => 'datetime'];
    }
}
