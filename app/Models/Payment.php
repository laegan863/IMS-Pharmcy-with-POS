<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * One tender on a sale. Card and e-wallet payments carry the terminal or
 * app reference number.
 *
 * @property int $id
 * @property int $sale_id
 * @property string $method
 * @property string $amount
 * @property string|null $reference
 * @property CarbonImmutable $created_at
 */
#[Fillable(['sale_id', 'method', 'amount', 'reference', 'created_at'])]
class Payment extends Model
{
    public const UPDATED_AT = null;

    public const METHODS = ['cash' => 'Cash', 'ewallet' => 'E-wallet', 'card' => 'Card'];

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
