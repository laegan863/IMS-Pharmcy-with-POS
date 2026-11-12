<?php

namespace App\Services\Sales;

use App\Enums\Permission;
use App\Enums\ProductClassification;
use App\Enums\StockMovementType;
use App\Models\PrescriptionLine;
use App\Models\Reading;
use App\Models\Sale;
use App\Models\SaleAdjustment;
use App\Models\SaleLine;
use App\Models\SaleLineBatch;
use App\Models\Shift;
use App\Models\User;
use App\Services\DocumentNumbers;
use App\Services\Stock\StockService;
use App\Settings\Settings;
use App\Support\Decimal;
use App\Support\SupervisorApproval;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Voids and refunds. Both are new documents with their own number series
 * that point at the original invoice; the invoice itself is never edited
 * apart from its status. Returned stock goes back to the batches it was sold
 * from. A supervisor approves each one with their PIN.
 *
 * A void cancels a whole sale and is only allowed before the terminal's
 * Z-reading for that day. A refund returns some or all of the items later.
 */
class SaleAdjustmentService
{
    public function __construct(
        private StockService $stock,
        private DocumentNumbers $numbers,
        private Settings $settings,
    ) {}

    public function void(Sale $sale, Shift $shift, User $user, string $reason, ?string $approver, ?string $pin): SaleAdjustment
    {
        $supervisor = $this->approve($approver, $pin, Permission::PosVoid, 'pos.supervisor_for_void', $user);

        return DB::transaction(function () use ($sale, $shift, $user, $reason, $supervisor): SaleAdjustment {
            $sale = Sale::withoutGlobalScopes()->lockForUpdate()->findOrFail($sale->id);
            $this->assertSameBranch($sale, $shift);

            if ($sale->status !== 'completed') {
                throw ValidationException::withMessages(['sale' => __('Only a completed sale with no refunds can be voided.')]);
            }

            $lastZ = Reading::withoutGlobalScopes()->where('terminal_id', $sale->terminal_id)->max('period_end');

            if ($lastZ !== null && $sale->completed_at->lte($lastZ)) {
                throw ValidationException::withMessages(['sale' => __('This sale is already in a Z-reading; refund it instead.')]);
            }

            $adjustment = SaleAdjustment::query()->create([
                'branch_id' => $sale->branch_id,
                'terminal_id' => $shift->terminal_id,
                'shift_id' => $shift->id,
                'sale_id' => $sale->id,
                'type' => 'void',
                'number' => $this->numbers->next(DocumentNumbers::VOID, $sale->branch_id, $shift->terminal_id),
                'reason' => $reason,
                'amount' => $sale->total,
                'vat_amount' => $sale->vat_amount,
                'discount_amount' => $sale->statutory_discount,
                'user_id' => $user->id,
                'approved_by' => $supervisor->id,
                'created_at' => now(),
            ]);

            foreach ($sale->lines()->with(['batches.batch', 'product'])->get() as $line) {
                $this->reverseDispensing($line, $line->quantity_base);
                $this->returnStock($line, $line->quantity_base, $adjustment, StockMovementType::SaleVoid);
                $adjustment->lines()->create(['sale_line_id' => $line->id, 'quantity_base' => $line->quantity_base, 'amount' => $line->line_total]);
                $line->update(['refunded_base' => $line->quantity_base]);
            }

            $sale->update(['status' => 'voided']);

            return $adjustment;
        });
    }

    /**
     * @param  array<int, int>  $quantities  base units to refund, keyed by sale line id
     */
    public function refund(Sale $sale, Shift $shift, User $user, array $quantities, string $reason, ?string $approver, ?string $pin): SaleAdjustment
    {
        $supervisor = $this->approve($approver, $pin, Permission::PosRefund, 'pos.supervisor_for_refund', $user);

        return DB::transaction(function () use ($sale, $shift, $user, $quantities, $reason, $supervisor): SaleAdjustment {
            $sale = Sale::withoutGlobalScopes()->lockForUpdate()->findOrFail($sale->id);
            $this->assertSameBranch($sale, $shift);

            if ($sale->status === 'voided') {
                throw ValidationException::withMessages(['sale' => __('A voided sale cannot be refunded.')]);
            }

            $lines = $sale->lines()->with(['batches.batch', 'product'])->get()->keyBy('id');
            $quantities = array_filter($quantities, fn (int $quantity) => $quantity > 0);

            if ($quantities === []) {
                throw ValidationException::withMessages(['lines' => __('Choose at least one item to refund.')]);
            }

            $adjustment = SaleAdjustment::query()->create([
                'branch_id' => $sale->branch_id,
                'terminal_id' => $shift->terminal_id,
                'shift_id' => $shift->id,
                'sale_id' => $sale->id,
                'type' => 'refund',
                'number' => $this->numbers->next(DocumentNumbers::REFUND, $sale->branch_id, $shift->terminal_id),
                'reason' => $reason,
                'amount' => '0.00',
                'user_id' => $user->id,
                'approved_by' => $supervisor->id,
                'created_at' => now(),
            ]);

            $amounts = [];
            $vat = [];
            $discounts = [];

            foreach ($quantities as $lineId => $quantity) {
                $line = $lines->get($lineId);

                if (! $line instanceof SaleLine) {
                    throw ValidationException::withMessages(['lines' => __('An item is not on this invoice.')]);
                }

                if ($quantity > $line->refundableBase()) {
                    throw ValidationException::withMessages(["lines.{$lineId}" => __('Only :count of :item can still be refunded.', ['count' => $line->refundableBase(), 'item' => $line->description])]);
                }

                // Refund the same share of what the customer actually paid.
                $share = Decimal::div($quantity, $line->quantity_base, 10);
                $amount = Decimal::mul($line->line_total, $share);
                $amounts[] = $amount;
                $vat[] = Decimal::mul($line->vat_amount, $share);
                $discounts[] = Decimal::mul($line->discount_amount, $share);

                $this->reverseDispensing($line, $quantity);
                $this->returnStock($line, $quantity, $adjustment, StockMovementType::Refund);
                $adjustment->lines()->create(['sale_line_id' => $line->id, 'quantity_base' => $quantity, 'amount' => $amount]);
                $line->increment('refunded_base', $quantity);
            }

            $adjustment->update([
                'amount' => Decimal::sum($amounts),
                'vat_amount' => Decimal::sum($vat),
                'discount_amount' => Decimal::sum($discounts),
            ]);

            $sale->update(['status' => 'refunded']);

            return $adjustment->refresh();
        });
    }

    /**
     * Puts units back into the batches the line took them from, latest
     * expiry first (the reverse of how they were allocated).
     */
    private function returnStock(SaleLine $line, int $quantity, SaleAdjustment $adjustment, StockMovementType $type): void
    {
        $remaining = $quantity;
        $batches = $line->batches->sortByDesc(fn (SaleLineBatch $batch) => $batch->batch->expires_on);

        foreach ($batches as $lineBatch) {
            $available = $lineBatch->quantity_base - $lineBatch->returned_base;
            $take = min($remaining, $available);

            if ($take <= 0) {
                continue;
            }

            $this->stock->adjust($adjustment->branch_id, $lineBatch->batch, $take, $type, $adjustment, $adjustment->number, $adjustment->reason, $lineBatch->unit_cost);
            $lineBatch->increment('returned_base', $take);
            $remaining -= $take;

            if ($remaining === 0) {
                break;
            }
        }
    }

    /**
     * Gives the prescription back its balance. Dangerous drug sales cannot be
     * voided or refunded here: the register is append-only, so the
     * pharmacist records the return with a stock adjustment instead.
     */
    private function reverseDispensing(SaleLine $line, int $quantity): void
    {
        if ($line->product->classification === ProductClassification::DangerousDrug) {
            throw ValidationException::withMessages(['sale' => __('Dangerous drug sales cannot be voided or refunded at the POS. The pharmacist records the return.')]);
        }

        if ($line->prescription_line_id !== null) {
            PrescriptionLine::query()->whereKey($line->prescription_line_id)->decrement('quantity_dispensed_base', $quantity);
        }
    }

    private function approve(?string $approver, ?string $pin, Permission $permission, string $setting, User $user): User
    {
        if ($this->settings->bool($setting)) {
            return SupervisorApproval::verify($approver, $pin, $permission);
        }

        if (! $user->can($permission->value)) {
            throw ValidationException::withMessages(['approver_pin' => __('You may not do this without a supervisor.')]);
        }

        return $user;
    }

    private function assertSameBranch(Sale $sale, Shift $shift): void
    {
        if ($sale->branch_id !== $shift->branch_id) {
            throw ValidationException::withMessages(['sale' => __('This sale belongs to another branch.')]);
        }
    }
}
