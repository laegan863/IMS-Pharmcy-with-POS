<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToBranch;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Cash put into or taken out of the drawer during a shift, other than sales.
 *
 * @property int $id
 * @property int $branch_id
 * @property int $shift_id
 * @property string $type
 * @property string $amount
 * @property string $reason
 * @property int $user_id
 * @property CarbonImmutable $created_at
 */
#[Fillable(['branch_id', 'shift_id', 'type', 'amount', 'reason', 'user_id', 'created_at'])]
class CashMovement extends Model
{
    use Auditable, BelongsToBranch;

    public const UPDATED_AT = null;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'created_at' => 'datetime'];
    }
}
