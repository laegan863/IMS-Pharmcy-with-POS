<?php

namespace App\Models;

use App\Enums\DocumentStatus;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToBranch;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Stock sent back to a supplier from named batches. Posting removes it.
 *
 * @property int $id
 * @property int $branch_id
 * @property string|null $number
 * @property int $supplier_id
 * @property DocumentStatus $status
 * @property string $reason
 * @property string $total
 * @property int|null $created_by
 * @property int|null $posted_by
 * @property CarbonImmutable|null $posted_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Supplier $supplier
 * @property-read Collection<int, SupplierReturnLine> $lines
 */
#[Fillable(['branch_id', 'number', 'supplier_id', 'status', 'reason', 'total', 'created_by', 'posted_by', 'posted_at'])]
class SupplierReturn extends Model
{
    use Auditable, BelongsToBranch;

    /**
     * @return BelongsTo<Supplier, $this>
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /**
     * @return HasMany<SupplierReturnLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(SupplierReturnLine::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => DocumentStatus::class,
            'posted_at' => 'datetime',
            'total' => 'decimal:2',
        ];
    }
}
