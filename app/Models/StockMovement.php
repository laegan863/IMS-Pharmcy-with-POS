<?php

namespace App\Models;

use App\Enums\StockMovementType;
use App\Models\Concerns\BelongsToBranch;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use LogicException;

/**
 * One line of the stock ledger. Movements are never updated or deleted; a
 * mistake is fixed with a reversing movement. Written only by StockService.
 *
 * @property int $id
 * @property int $branch_id
 * @property int $batch_id
 * @property int $product_id
 * @property int $quantity
 * @property StockMovementType $type
 * @property string $unit_cost
 * @property string|null $source_type
 * @property int|null $source_id
 * @property string|null $reference
 * @property string|null $notes
 * @property int|null $user_id
 * @property CarbonImmutable $created_at
 * @property-read Batch $batch
 * @property-read Product $product
 * @property-read User|null $user
 */
#[Fillable(['branch_id', 'batch_id', 'product_id', 'quantity', 'type', 'unit_cost', 'source_type', 'source_id', 'reference', 'notes', 'user_id', 'created_at'])]
class StockMovement extends Model
{
    use BelongsToBranch;

    public const UPDATED_AT = null;

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Stock movements cannot be changed. Post a reversing movement.'));
        static::deleting(fn () => throw new LogicException('Stock movements cannot be deleted. Post a reversing movement.'));
    }

    /**
     * @return BelongsTo<Batch, $this>
     */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class);
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'type' => StockMovementType::class,
            'unit_cost' => 'decimal:4',
            'created_at' => 'datetime',
        ];
    }
}
