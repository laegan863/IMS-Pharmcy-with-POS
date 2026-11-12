<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * A cart parked with "hold" so the cashier can serve the next customer. It
 * is not a document: no number, no stock, deleted when resumed.
 *
 * @property int $id
 * @property int $branch_id
 * @property int $terminal_id
 * @property int $user_id
 * @property string $label
 * @property array<string, mixed> $cart
 * @property CarbonImmutable|null $created_at
 */
#[Fillable(['branch_id', 'terminal_id', 'user_id', 'label', 'cart'])]
class HeldSale extends Model
{
    use BelongsToBranch;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['cart' => 'array'];
    }
}
