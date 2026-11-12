<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * A Z-reading: the end-of-day total for one terminal. Never changed.
 *
 * @property int $id
 * @property int $branch_id
 * @property int $terminal_id
 * @property string $type
 * @property string $number
 * @property int $z_counter
 * @property CarbonImmutable $period_start
 * @property CarbonImmutable $period_end
 * @property string $grand_total_before
 * @property string $grand_total_after
 * @property array<string, mixed> $data
 * @property int $user_id
 * @property CarbonImmutable $created_at
 * @property-read Terminal $terminal
 * @property-read User $user
 */
#[Fillable(['branch_id', 'terminal_id', 'type', 'number', 'z_counter', 'period_start', 'period_end', 'grand_total_before', 'grand_total_after', 'data', 'user_id', 'created_at'])]
class Reading extends Model
{
    use BelongsToBranch;

    public const UPDATED_AT = null;

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Readings cannot be changed.'));
        static::deleting(fn () => throw new LogicException('Readings cannot be deleted.'));
    }

    /**
     * @return BelongsTo<Terminal, $this>
     */
    public function terminal(): BelongsTo
    {
        return $this->belongsTo(Terminal::class)->withoutGlobalScopes();
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
        return [
            'period_start' => 'datetime',
            'period_end' => 'datetime',
            'grand_total_before' => 'decimal:2',
            'grand_total_after' => 'decimal:2',
            'data' => 'array',
            'created_at' => 'datetime',
            'z_counter' => 'integer',
        ];
    }
}
