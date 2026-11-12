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
 * Goods received from a supplier, against a purchase order or directly.
 * Posting adds the stock; a posted receipt is never edited.
 *
 * @property int $id
 * @property int $branch_id
 * @property string|null $number
 * @property int|null $purchase_order_id
 * @property int $supplier_id
 * @property string|null $supplier_invoice_number
 * @property CarbonImmutable|null $supplier_invoice_date
 * @property DocumentStatus $status
 * @property string|null $notes
 * @property string $total
 * @property int|null $created_by
 * @property int|null $posted_by
 * @property CarbonImmutable|null $posted_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Supplier $supplier
 * @property-read PurchaseOrder|null $purchaseOrder
 * @property-read Collection<int, GoodsReceiptLine> $lines
 * @property-read User|null $poster
 */
#[Fillable(['branch_id', 'number', 'purchase_order_id', 'supplier_id', 'supplier_invoice_number', 'supplier_invoice_date', 'status', 'notes', 'total', 'created_by', 'posted_by', 'posted_at'])]
class GoodsReceipt extends Model
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
     * @return BelongsTo<PurchaseOrder, $this>
     */
    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    /**
     * @return HasMany<GoodsReceiptLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(GoodsReceiptLine::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function poster(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by');
    }

    public function isPosted(): bool
    {
        return $this->status === DocumentStatus::Posted;
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
            'supplier_invoice_date' => 'date',
            'posted_at' => 'datetime',
            'total' => 'decimal:2',
        ];
    }
}
