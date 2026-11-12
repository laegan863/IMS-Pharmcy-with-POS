<?php

namespace App\Services\Stock;

use App\Enums\AdjustmentReason;
use App\Enums\BatchStatus;
use App\Enums\Permission;
use App\Enums\StockMovementType;
use App\Models\Batch;
use App\Models\Scopes\BranchScope;
use App\Models\StockAdjustment;
use App\Models\StockBalance;
use App\Models\StockCount;
use App\Models\StockTransfer;
use App\Models\User;
use App\Services\DocumentNumbers;
use App\Support\Decimal;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Adjustments, disposals, physical counts, branch transfers and batch
 * quarantine. Every stock change goes through StockService; documents are
 * drafted by staff and posted by someone allowed to approve them.
 */
class StockControlService
{
    public function __construct(private StockService $stock, private DocumentNumbers $numbers) {}

    // ------------------------------------------------------------------
    // Adjustments and disposals
    // ------------------------------------------------------------------

    /**
     * @param  array{reason_code: string, note?: string|null, disposal_method?: string|null, witness_name?: string|null}  $header
     * @param  list<array{batch_id: int, quantity_change: int}>  $lines
     */
    public function saveAdjustment(?StockAdjustment $adjustment, int $branchId, string $type, array $header, array $lines, User $user): StockAdjustment
    {
        if ($adjustment !== null && $adjustment->status === 'posted') {
            throw ValidationException::withMessages(['status' => __('A posted document cannot be edited.')]);
        }

        if ($type === 'disposal' && (blank($header['disposal_method'] ?? null) || blank($header['witness_name'] ?? null))) {
            throw ValidationException::withMessages(['witness_name' => __('A disposal needs the method and a witness.')]);
        }

        $batches = Batch::query()->whereIn('id', array_column($lines, 'batch_id'))->get()->keyBy('id');

        return DB::transaction(function () use ($adjustment, $branchId, $type, $header, $lines, $user, $batches): StockAdjustment {
            $adjustment ??= new StockAdjustment(['branch_id' => $branchId, 'type' => $type, 'status' => 'draft', 'created_by' => $user->id]);
            $adjustment->fill($header)->save();
            $adjustment->lines()->delete();
            $values = [];

            foreach ($lines as $index => $line) {
                $batch = $batches->get($line['batch_id']);
                $change = $type === 'disposal' ? -abs($line['quantity_change']) : $line['quantity_change'];

                if ($batch === null || $change === 0) {
                    throw ValidationException::withMessages(["lines.{$index}.quantity_change" => __('Choose a batch and a quantity other than zero.')]);
                }

                $adjustment->lines()->create(['batch_id' => $batch->id, 'product_id' => $batch->product_id, 'quantity_change' => $change]);
                $values[] = Decimal::mul($change, $batch->unit_cost);
            }

            $adjustment->value = Decimal::sum($values);
            $adjustment->save();

            return $adjustment->refresh();
        });
    }

    public function postAdjustment(StockAdjustment $adjustment, User $approver): StockAdjustment
    {
        if (! $approver->can(Permission::StockAdjustApprove->value)) {
            throw ValidationException::withMessages(['status' => __('Only a pharmacist or the owner can approve this.')]);
        }

        return DB::transaction(function () use ($adjustment, $approver): StockAdjustment {
            $adjustment = StockAdjustment::withoutGlobalScopes()->lockForUpdate()->findOrFail($adjustment->id);

            if ($adjustment->status === 'posted') {
                throw ValidationException::withMessages(['status' => __('This document is already posted.')]);
            }

            $adjustment->load('lines.batch');

            if ($adjustment->lines->isEmpty()) {
                throw ValidationException::withMessages(['lines' => __('Add at least one line.')]);
            }

            $series = $adjustment->isDisposal() ? DocumentNumbers::DISPOSAL : DocumentNumbers::ADJUSTMENT;
            $adjustment->number = $this->numbers->next($series, $adjustment->branch_id);
            $type = $adjustment->isDisposal() ? StockMovementType::Disposal : StockMovementType::Adjustment;
            $note = $adjustment->reason_code->label().($adjustment->note ? ': '.$adjustment->note : '');

            foreach ($adjustment->lines as $index => $line) {
                try {
                    $this->stock->adjust($adjustment->branch_id, $line->batch, $line->quantity_change, $type, $adjustment, $adjustment->number, $note);
                } catch (InsufficientStockException $exception) {
                    throw ValidationException::withMessages(["lines.{$index}.quantity_change" => __('Only :available on hand in this batch.', ['available' => $exception->available])]);
                }

                if ($adjustment->isDisposal() && $this->batchEmptyEverywhere($line->batch_id)) {
                    $line->batch->update(['status' => BatchStatus::Disposed, 'status_reason' => $adjustment->number]);
                }
            }

            $adjustment->fill(['status' => 'posted', 'approved_by' => $approver->id, 'posted_at' => now()])->save();

            return $adjustment;
        });
    }

    // ------------------------------------------------------------------
    // Physical counts
    // ------------------------------------------------------------------

    /**
     * Starts a count and freezes the system quantity of every batch in scope.
     *
     * @param  list<int>  $productIds  empty for every product with stock
     */
    public function startCount(int $branchId, array $productIds, ?int $categoryId, User $user): StockCount
    {
        return DB::transaction(function () use ($branchId, $productIds, $categoryId, $user): StockCount {
            $balances = StockBalance::withoutGlobalScope(BranchScope::class)
                ->where('branch_id', $branchId)
                ->where('quantity', '>', 0)
                ->when($productIds !== [], fn ($query) => $query->whereIn('product_id', $productIds))
                ->when($categoryId !== null, fn ($query) => $query->whereHas('product', fn ($product) => $product->where('category_id', $categoryId)))
                ->get();

            if ($balances->isEmpty()) {
                throw ValidationException::withMessages(['scope' => __('Nothing in stock matches this count.')]);
            }

            $count = StockCount::query()->create([
                'branch_id' => $branchId,
                'number' => $this->numbers->next(DocumentNumbers::STOCK_COUNT, $branchId),
                'status' => 'counting',
                'scope' => $productIds !== [] ? __(':count products', ['count' => count($productIds)]) : ($categoryId !== null ? __('One category') : __('All stock')),
                'created_by' => $user->id,
            ]);

            foreach ($balances as $balance) {
                $count->lines()->create([
                    'batch_id' => $balance->batch_id,
                    'product_id' => $balance->product_id,
                    'system_quantity' => $balance->quantity,
                ]);
            }

            return $count;
        });
    }

    /**
     * @param  array<int, int|null>  $counted  counted quantity keyed by line id
     */
    public function recordCounts(StockCount $count, array $counted): void
    {
        if ($count->status === 'posted') {
            throw ValidationException::withMessages(['status' => __('This count is already posted.')]);
        }

        $changed = false;

        foreach ($count->lines()->whereIn('id', array_keys($counted))->get() as $line) {
            $value = $counted[$line->id] === null ? null : max(0, $counted[$line->id]);

            if ($value !== $line->counted_quantity) {
                $line->update(['counted_quantity' => $value]);
                $changed = true;
            }
        }

        // Changing a reviewed count sends it back for review.
        if ($changed && $count->status === 'reviewed') {
            $count->update(['status' => 'counting', 'reviewed_by' => null]);
        }
    }

    public function review(StockCount $count, User $user): void
    {
        if ($count->lines()->whereNull('counted_quantity')->exists()) {
            throw ValidationException::withMessages(['lines' => __('Enter a count for every line before review.')]);
        }

        $count->update(['status' => 'reviewed', 'reviewed_by' => $user->id]);
    }

    /**
     * Posts only the variances (counted − frozen system quantity), so sales
     * made while counting are not counted twice.
     */
    public function postCount(StockCount $count, User $approver): StockCount
    {
        if (! $approver->can(Permission::StockCountApprove->value)) {
            throw ValidationException::withMessages(['status' => __('Only a pharmacist or the owner can post a count.')]);
        }

        if ($count->status !== 'reviewed') {
            throw ValidationException::withMessages(['status' => __('Review the count before posting.')]);
        }

        return DB::transaction(function () use ($count, $approver): StockCount {
            $count = StockCount::withoutGlobalScopes()->lockForUpdate()->findOrFail($count->id);
            $count->load('lines.batch');

            foreach ($count->lines as $line) {
                $variance = $line->variance() ?? 0;

                if ($variance === 0) {
                    continue;
                }

                try {
                    $this->stock->adjust($count->branch_id, $line->batch, $variance, StockMovementType::CountVariance, $count, $count->number, __('Physical count'));
                } catch (InsufficientStockException) {
                    throw ValidationException::withMessages(['lines' => __('Lot :lot sold more than its count allows since counting began; recount it.', ['lot' => $line->batch->lot_number])]);
                }
            }

            $count->fill(['status' => 'posted', 'posted_by' => $approver->id, 'posted_at' => now()])->save();

            return $count;
        });
    }

    // ------------------------------------------------------------------
    // Branch transfers
    // ------------------------------------------------------------------

    /**
     * @param  list<array{batch_id: int, quantity_base: int}>  $lines
     */
    public function saveTransfer(?StockTransfer $transfer, int $fromBranchId, int $toBranchId, ?string $note, array $lines, User $user): StockTransfer
    {
        if ($fromBranchId === $toBranchId) {
            throw ValidationException::withMessages(['to_branch_id' => __('Choose a different branch.')]);
        }

        if ($transfer !== null && $transfer->status !== 'draft') {
            throw ValidationException::withMessages(['status' => __('Only a draft transfer can be edited.')]);
        }

        $batches = Batch::query()->whereIn('id', array_column($lines, 'batch_id'))->get()->keyBy('id');

        return DB::transaction(function () use ($transfer, $fromBranchId, $toBranchId, $note, $lines, $user, $batches): StockTransfer {
            $transfer ??= new StockTransfer(['branch_id' => $fromBranchId, 'status' => 'draft', 'created_by' => $user->id]);
            $transfer->fill(['to_branch_id' => $toBranchId, 'note' => $note])->save();
            $transfer->lines()->delete();

            foreach ($lines as $index => $line) {
                $batch = $batches->get($line['batch_id']);

                if ($batch === null || $line['quantity_base'] < 1) {
                    throw ValidationException::withMessages(["lines.{$index}.quantity_base" => __('Choose a batch and a quantity.')]);
                }

                $transfer->lines()->create(['batch_id' => $batch->id, 'product_id' => $batch->product_id, 'quantity_base' => $line['quantity_base']]);
            }

            return $transfer->refresh();
        });
    }

    public function dispatch(StockTransfer $transfer, User $user): StockTransfer
    {
        return DB::transaction(function () use ($transfer, $user): StockTransfer {
            $transfer = StockTransfer::query()->lockForUpdate()->findOrFail($transfer->id);

            if ($transfer->status !== 'draft' || $user->branch_id !== $transfer->branch_id) {
                throw ValidationException::withMessages(['status' => __('Only the sending branch can dispatch a draft transfer.')]);
            }

            $transfer->load('lines.batch');
            $transfer->number = $this->numbers->next(DocumentNumbers::TRANSFER, $transfer->branch_id);

            foreach ($transfer->lines as $index => $line) {
                try {
                    $this->stock->adjust($transfer->branch_id, $line->batch, -$line->quantity_base, StockMovementType::TransferOut, $transfer, $transfer->number);
                } catch (InsufficientStockException $exception) {
                    throw ValidationException::withMessages(["lines.{$index}.quantity_base" => __('Only :available on hand in this batch.', ['available' => $exception->available])]);
                }
            }

            $transfer->fill(['status' => 'in_transit', 'sent_by' => $user->id, 'dispatched_at' => now()])->save();

            return $transfer;
        });
    }

    public function receive(StockTransfer $transfer, User $user): StockTransfer
    {
        return DB::transaction(function () use ($transfer, $user): StockTransfer {
            $transfer = StockTransfer::query()->lockForUpdate()->findOrFail($transfer->id);

            if ($transfer->status !== 'in_transit' || $user->branch_id !== $transfer->to_branch_id) {
                throw ValidationException::withMessages(['status' => __('Only the receiving branch can receive a transfer in transit.')]);
            }

            $transfer->load('lines.batch');

            foreach ($transfer->lines as $line) {
                $this->stock->adjust($transfer->to_branch_id, $line->batch, $line->quantity_base, StockMovementType::TransferIn, $transfer, $transfer->number);
            }

            $transfer->fill(['status' => 'received', 'received_by' => $user->id, 'received_at' => now()])->save();

            return $transfer;
        });
    }

    // ------------------------------------------------------------------
    // Batch status
    // ------------------------------------------------------------------

    public function quarantine(Batch $batch, string $reason): void
    {
        if (in_array($batch->status, [BatchStatus::Disposed], true)) {
            throw ValidationException::withMessages(['status' => __('A disposed batch cannot be quarantined.')]);
        }

        $batch->update(['status' => BatchStatus::Quarantined, 'status_reason' => $reason]);
    }

    public function release(Batch $batch): void
    {
        if ($batch->status !== BatchStatus::Quarantined) {
            throw ValidationException::withMessages(['status' => __('Only a quarantined batch can be released.')]);
        }

        $batch->update([
            'status' => $batch->isExpired() ? BatchStatus::Expired : BatchStatus::Active,
            'status_reason' => null,
        ]);
    }

    /**
     * Marks active batches past their expiry date as expired. Run daily.
     */
    public function expireBatches(): int
    {
        return Batch::query()
            ->where('status', BatchStatus::Active->value)
            ->whereDate('expires_on', '<', today())
            ->update(['status' => BatchStatus::Expired->value, 'status_reason' => 'Past expiry date', 'updated_at' => now()]);
    }

    private function batchEmptyEverywhere(int $batchId): bool
    {
        return (int) StockBalance::withoutGlobalScope(BranchScope::class)->where('batch_id', $batchId)->sum('quantity') === 0;
    }

    /**
     * Reasons offered on the adjustment form.
     *
     * @return list<array{value: string, label: string}>
     */
    public static function reasons(): array
    {
        return AdjustmentReason::options();
    }
}
