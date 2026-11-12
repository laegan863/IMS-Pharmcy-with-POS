<?php

namespace App\Models;

use App\Enums\BatchStatus;
use App\Models\Concerns\Auditable;
use Carbon\CarbonImmutable;
use Database\Factories\BatchFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A lot of one product with one expiry date. Batches are shared by all
 * branches; how much each branch holds is in stock_balances.
 *
 * A batch is usable through its expiry date and expired from the next day.
 *
 * @property int $id
 * @property int $product_id
 * @property string $lot_number
 * @property CarbonImmutable $expires_on
 * @property string $unit_cost
 * @property int|null $supplier_id
 * @property BatchStatus $status
 * @property string|null $status_reason
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Product $product
 * @property-read Supplier|null $supplier
 */
#[Fillable(['product_id', 'lot_number', 'expires_on', 'unit_cost', 'supplier_id', 'status', 'status_reason'])]
class Batch extends Model
{
    /** @use HasFactory<BatchFactory> */
    use Auditable, HasFactory;

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @return BelongsTo<Supplier, $this>
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /**
     * @return HasMany<StockBalance, $this>
     */
    public function balances(): HasMany
    {
        return $this->hasMany(StockBalance::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_on->lt(today());
    }

    /**
     * Whether this batch may be sold or issued today.
     */
    public function isSellable(): bool
    {
        return $this->status === BatchStatus::Active && ! $this->isExpired();
    }

    /**
     * Days until expiry; negative once expired.
     */
    public function daysToExpiry(): int
    {
        return (int) today()->diffInDays($this->expires_on, false);
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
            'unit_cost' => 'decimal:4',
            'status' => BatchStatus::class,
        ];
    }
}
