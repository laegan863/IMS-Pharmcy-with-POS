<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Price history for one product unit. Rows are only ever added.
 *
 * @property int $id
 * @property int $product_unit_id
 * @property string|null $old_price
 * @property string|null $new_price
 * @property int|null $changed_by
 * @property CarbonImmutable $changed_at
 * @property-read User|null $changer
 */
#[Fillable(['product_unit_id', 'old_price', 'new_price', 'changed_by', 'changed_at'])]
class ProductPriceChange extends Model
{
    public $timestamps = false;

    /**
     * @return BelongsTo<User, $this>
     */
    public function changer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'old_price' => 'decimal:2',
            'new_price' => 'decimal:2',
            'changed_at' => 'datetime',
        ];
    }
}
