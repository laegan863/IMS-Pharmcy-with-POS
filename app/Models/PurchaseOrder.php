<?php

namespace App\Models;

use App\Enums\PurchaseOrderStatus;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToBranch;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An order to a supplier. It never moves stock; goods receipts do.
 *
 * @property int $id
 * @property int $branch_id
 * @property string $number
 * @property int $supplier_id
 * @property PurchaseOrderStatus $status
 * @property CarbonImmutable|null $expected_on
 * @property string|null $notes
 * @property string $total
 * @property int|null $created_by
 * @property int|null $approved_by
 * @property CarbonImmutable|null $approved_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Supplier $supplier
 * @property-read Collection<int, PurchaseOrderLine> $lines
 * @property-read User|null $creator
 * @property-read User|null $approver
 */
#[Fillable(['branch_id', 'number', 'supplier_id', 'status', 'expected_on', 'notes', 'total', 'created_by', 'approved_by', 'approved_at'])]
class PurchaseOrder extends Model
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
     * @return HasMany<PurchaseOrderLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(PurchaseOrderLine::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => PurchaseOrderStatus::class,
            'expected_on' => 'date',
            'approved_at' => 'datetime',
            'total' => 'decimal:2',
        ];
    }
}
