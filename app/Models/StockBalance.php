<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * How many base units of one batch one branch holds. Equal to the sum of the
 * branch's movements for the batch; stock:verify-ledger checks this nightly.
 * Written only by App\Services\Stock\StockService.
 *
 * @property int $id
 * @property int $branch_id
 * @property int $batch_id
 * @property int $product_id
 * @property int $quantity
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Batch $batch
 * @property-read Product $product
 */
#[Fillable(['branch_id', 'batch_id', 'product_id', 'quantity'])]
class StockBalance extends Model
{
    use BelongsToBranch;

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
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['quantity' => 'integer'];
    }
}
