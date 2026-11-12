<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToBranch;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A cashier's session on one terminal, from opening cash to cash count.
 *
 * @property int $id
 * @property int $branch_id
 * @property int $terminal_id
 * @property int $user_id
 * @property string $number
 * @property string $status
 * @property string $opening_cash
 * @property CarbonImmutable $opened_at
 * @property string|null $expected_cash
 * @property string|null $counted_cash
 * @property string|null $variance
 * @property array<string, int>|null $denominations
 * @property array<string, mixed>|null $x_reading
 * @property CarbonImmutable|null $closed_at
 * @property-read Terminal $terminal
 * @property-read User $user
 */
#[Fillable(['branch_id', 'terminal_id', 'user_id', 'number', 'status', 'opening_cash', 'opened_at', 'expected_cash', 'counted_cash', 'variance', 'denominations', 'x_reading', 'closed_at'])]
class Shift extends Model
{
    use Auditable, BelongsToBranch;

    public function isOpen(): bool
    {
        return $this->status === 'open';
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
     * @return HasMany<Sale, $this>
     */
    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    /**
     * @return HasMany<CashMovement, $this>
     */
    public function cashMovements(): HasMany
    {
        return $this->hasMany(CashMovement::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'opening_cash' => 'decimal:2',
            'expected_cash' => 'decimal:2',
            'counted_cash' => 'decimal:2',
            'variance' => 'decimal:2',
            'denominations' => 'array',
            'x_reading' => 'array',
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }
}
