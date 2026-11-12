<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Stock sent from one branch to another. It is not branch-scoped because
 * both the sending and the receiving branch need to see it; use
 * involving() to list a branch's transfers.
 *
 * @property int $id
 * @property int $branch_id
 * @property int $to_branch_id
 * @property string|null $number
 * @property string $status
 * @property string|null $note
 * @property int $created_by
 * @property int|null $sent_by
 * @property CarbonImmutable|null $dispatched_at
 * @property int|null $received_by
 * @property CarbonImmutable|null $received_at
 * @property CarbonImmutable|null $created_at
 * @property-read Collection<int, StockTransferLine> $lines
 * @property-read Branch $fromBranch
 * @property-read Branch $toBranch
 */
#[Fillable(['branch_id', 'to_branch_id', 'number', 'status', 'note', 'created_by', 'sent_by', 'dispatched_at', 'received_by', 'received_at'])]
class StockTransfer extends Model
{
    use Auditable;

    /**
     * Transfers sent from or to the branch.
     *
     * @param  Builder<StockTransfer>  $query
     */
    public function scopeInvolving(Builder $query, int $branchId): void
    {
        $query->where(fn (Builder $query) => $query->where('branch_id', $branchId)->orWhere('to_branch_id', $branchId));
    }

    /**
     * @return HasMany<StockTransferLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(StockTransferLine::class);
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function fromBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function toBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'to_branch_id');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['dispatched_at' => 'datetime', 'received_at' => 'datetime'];
    }
}
