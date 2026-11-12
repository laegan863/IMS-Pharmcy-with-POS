<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToBranch;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A void or refund. It has its own number series and points at the original
 * invoice, which is never changed apart from its status.
 *
 * @property int $id
 * @property int $branch_id
 * @property int $terminal_id
 * @property int $shift_id
 * @property int $sale_id
 * @property string $type
 * @property string $number
 * @property string $reason
 * @property string $amount
 * @property string $vat_amount
 * @property string $discount_amount
 * @property int $user_id
 * @property int $approved_by
 * @property CarbonImmutable $created_at
 * @property-read Sale $sale
 * @property-read Collection<int, SaleAdjustmentLine> $lines
 * @property-read User $approver
 * @property-read User $user
 */
#[Fillable(['branch_id', 'terminal_id', 'shift_id', 'sale_id', 'type', 'number', 'reason', 'amount', 'vat_amount', 'discount_amount', 'user_id', 'approved_by', 'created_at'])]
class SaleAdjustment extends Model
{
    use Auditable, BelongsToBranch;

    public const UPDATED_AT = null;

    /**
     * @return BelongsTo<Sale, $this>
     */
    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    /**
     * @return HasMany<SaleAdjustmentLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(SaleAdjustmentLine::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'vat_amount' => 'decimal:2', 'discount_amount' => 'decimal:2', 'created_at' => 'datetime'];
    }
}
