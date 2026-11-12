<?php

namespace App\Models;

use App\Enums\AdjustmentReason;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToBranch;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A stock adjustment or a disposal of expired or damaged stock.
 *
 * @property int $id
 * @property int $branch_id
 * @property string $type
 * @property string|null $number
 * @property string $status
 * @property AdjustmentReason $reason_code
 * @property string|null $note
 * @property string|null $disposal_method
 * @property string|null $witness_name
 * @property string $value
 * @property int $created_by
 * @property int|null $approved_by
 * @property CarbonImmutable|null $posted_at
 * @property CarbonImmutable|null $created_at
 * @property-read Collection<int, StockAdjustmentLine> $lines
 * @property-read User $creator
 * @property-read User|null $approver
 */
#[Fillable(['branch_id', 'type', 'number', 'status', 'reason_code', 'note', 'disposal_method', 'witness_name', 'value', 'created_by', 'approved_by', 'posted_at'])]
class StockAdjustment extends Model
{
    use Auditable, BelongsToBranch;

    /**
     * @return HasMany<StockAdjustmentLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(StockAdjustmentLine::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function isDisposal(): bool
    {
        return $this->type === 'disposal';
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['reason_code' => AdjustmentReason::class, 'value' => 'decimal:2', 'posted_at' => 'datetime'];
    }
}
