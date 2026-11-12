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
 * A physical count: counting, then reviewed, then posted.
 *
 * @property int $id
 * @property int $branch_id
 * @property string|null $number
 * @property string $status
 * @property string $scope
 * @property int $created_by
 * @property int|null $reviewed_by
 * @property int|null $posted_by
 * @property CarbonImmutable|null $posted_at
 * @property CarbonImmutable|null $created_at
 * @property-read Collection<int, StockCountLine> $lines
 * @property-read User $creator
 */
#[Fillable(['branch_id', 'number', 'status', 'scope', 'created_by', 'reviewed_by', 'posted_by', 'posted_at'])]
class StockCount extends Model
{
    use Auditable, BelongsToBranch;

    /**
     * @return HasMany<StockCountLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(StockCountLine::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['posted_at' => 'datetime'];
    }
}
