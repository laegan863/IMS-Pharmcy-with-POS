<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToBranch;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A sales invoice. Never edited once completed: voids and refunds are
 * SaleAdjustment documents that point back here.
 *
 * @property int $id
 * @property int $branch_id
 * @property int $terminal_id
 * @property int $shift_id
 * @property int $user_id
 * @property string $number
 * @property string $client_uuid
 * @property string $status
 * @property int|null $customer_id
 * @property string $discount_type
 * @property string|null $discount_id_number
 * @property string|null $discount_holder_name
 * @property string $gross_amount
 * @property string $vatable_sales
 * @property string $vat_amount
 * @property string $vat_exempt_sales
 * @property string $zero_rated_sales
 * @property string $statutory_discount
 * @property string $total
 * @property string $amount_paid
 * @property string $change_due
 * @property string $cost_total
 * @property CarbonImmutable|null $printed_at
 * @property int $reprint_count
 * @property CarbonImmutable $completed_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Collection<int, SaleLine> $lines
 * @property-read Collection<int, Payment> $payments
 * @property-read Collection<int, SaleAdjustment> $adjustments
 * @property-read Terminal $terminal
 * @property-read Branch $branch
 * @property-read User $cashier
 * @property-read Customer|null $customer
 */
#[Fillable([
    'branch_id', 'terminal_id', 'shift_id', 'user_id', 'number', 'client_uuid', 'status', 'customer_id',
    'discount_type', 'discount_id_number', 'discount_holder_name', 'gross_amount', 'vatable_sales', 'vat_amount',
    'vat_exempt_sales', 'zero_rated_sales', 'statutory_discount', 'total', 'amount_paid', 'change_due', 'cost_total',
    'printed_at', 'reprint_count', 'completed_at',
])]
#[Hidden(['discount_id_number'])]
class Sale extends Model
{
    use Auditable, BelongsToBranch;

    /**
     * @return HasMany<SaleLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(SaleLine::class);
    }

    /**
     * @return HasMany<Payment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * @return HasMany<SaleAdjustment, $this>
     */
    public function adjustments(): HasMany
    {
        return $this->hasMany(SaleAdjustment::class);
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
    public function cashier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Only the sale's own amounts and status are logged; the ID number is encrypted and kept out.
     *
     * @return list<string>
     */
    protected function auditExcept(): array
    {
        return ['discount_id_number'];
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'discount_id_number' => 'encrypted',
            'gross_amount' => 'decimal:2',
            'vatable_sales' => 'decimal:2',
            'vat_amount' => 'decimal:2',
            'vat_exempt_sales' => 'decimal:2',
            'zero_rated_sales' => 'decimal:2',
            'statutory_discount' => 'decimal:2',
            'total' => 'decimal:2',
            'amount_paid' => 'decimal:2',
            'change_due' => 'decimal:2',
            'cost_total' => 'decimal:4',
            'reprint_count' => 'integer',
            'completed_at' => 'datetime',
            'printed_at' => 'datetime',
        ];
    }
}
