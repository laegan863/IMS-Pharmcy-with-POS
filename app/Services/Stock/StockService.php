<?php

namespace App\Services\Stock;

use App\Enums\BatchStatus;
use App\Enums\StockMovementType;
use App\Models\Batch;
use App\Models\Product;
use App\Models\Scopes\BranchScope;
use App\Models\StockBalance;
use App\Models\StockMovement;
use App\Support\Decimal;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * The only code allowed to change stock. Every call writes immutable
 * stock_movements rows and updates stock_balances in the same transaction,
 * locking the balance rows it touches so two terminals cannot sell the same
 * unit.
 *
 * Quantities are in base units. Costs are per base unit, 4 decimals.
 * Callers that post a whole document (a sale, a receipt) wrap all their
 * calls in one DB::transaction so the document is all-or-nothing.
 */
class StockService
{
    /**
     * Adds stock to a batch, creating the batch if needed. Updates the
     * batch's cost and the product's moving average cost.
     */
    public function receive(
        int $branchId,
        Product $product,
        string $lotNumber,
        DateTimeInterface|string $expiresOn,
        int $quantity,
        string $unitCost,
        StockMovementType $type = StockMovementType::Receipt,
        ?Model $source = null,
        ?string $reference = null,
        ?int $supplierId = null,
        ?string $notes = null,
    ): StockMovement {
        $this->assertPositive($quantity);

        return DB::transaction(function () use ($branchId, $product, $lotNumber, $expiresOn, $quantity, $unitCost, $type, $source, $reference, $supplierId, $notes): StockMovement {
            $lockedProduct = Product::query()->lockForUpdate()->findOrFail($product->id);
            $onHandEverywhere = $this->totalOnHand($product->id);

            $expiryDate = $expiresOn instanceof DateTimeInterface ? $expiresOn->format('Y-m-d') : substr($expiresOn, 0, 10);

            $batch = Batch::query()
                ->where('product_id', $product->id)
                ->where('lot_number', trim($lotNumber))
                ->whereDate('expires_on', $expiryDate)
                ->lockForUpdate()
                ->first()
                ?? Batch::query()->create([
                    'product_id' => $product->id,
                    'lot_number' => trim($lotNumber),
                    'expires_on' => $expiryDate,
                    'unit_cost' => Decimal::round($unitCost, Decimal::COST),
                    'supplier_id' => $supplierId,
                    'status' => BatchStatus::Active,
                ]);

            if (! $batch->wasRecentlyCreated) {
                $batchOnHand = (int) StockBalance::withoutGlobalScope(BranchScope::class)->where('batch_id', $batch->id)->sum('quantity');
                $batch->unit_cost = $this->weightedAverage($batchOnHand, $batch->unit_cost, $quantity, $unitCost);
                $batch->save();
            }

            $lockedProduct->average_cost = $this->weightedAverage($onHandEverywhere, $lockedProduct->average_cost, $quantity, $unitCost);
            $lockedProduct->save();

            return $this->post($branchId, $batch, $quantity, $type, Decimal::round($unitCost, Decimal::COST), $source, $reference, $notes);
        });
    }

    /**
     * Takes stock out, first expiry first out, across as many batches as
     * needed. Expired, quarantined and disposed batches are never used.
     * A supervisor may name one batch instead ($batchId).
     *
     * @return list<Allocation>
     *
     * @throws InsufficientStockException
     */
    public function issue(
        int $branchId,
        Product $product,
        int $quantity,
        StockMovementType $type,
        ?Model $source = null,
        ?string $reference = null,
        ?int $batchId = null,
        ?string $notes = null,
    ): array {
        $this->assertPositive($quantity);

        return DB::transaction(function () use ($branchId, $product, $quantity, $type, $source, $reference, $batchId, $notes): array {
            $balances = $this->sellableBalances($branchId, $product->id)
                ->when($batchId !== null, fn (Builder $query) => $query->where('stock_balances.batch_id', $batchId))
                ->lockForUpdate()
                ->get();

            $available = (int) $balances->sum('quantity');

            if ($available < $quantity) {
                throw new InsufficientStockException($product->id, $product->displayName(), $quantity, $available);
            }

            $allocations = [];
            $remaining = $quantity;

            foreach ($balances as $balance) {
                if ($remaining === 0) {
                    break;
                }

                $take = min($remaining, $balance->quantity);
                $batch = Batch::query()->findOrFail($balance->batch_id);
                $movement = $this->post($branchId, $batch, -$take, $type, $batch->unit_cost, $source, $reference, $notes);

                $allocations[] = new Allocation($batch->id, $take, $batch->unit_cost, $movement->id);
                $remaining -= $take;
            }

            return $allocations;
        });
    }

    /**
     * Adds or removes stock on one named batch: count variances, adjustments,
     * disposals, transfers in, and returns of sold stock to the batch it
     * came from. Unlike issue(), this may touch expired or quarantined batches.
     *
     * @throws InsufficientStockException when removing more than the branch holds
     */
    public function adjust(
        int $branchId,
        Batch $batch,
        int $delta,
        StockMovementType $type,
        ?Model $source = null,
        ?string $reference = null,
        ?string $notes = null,
        ?string $unitCost = null,
    ): StockMovement {
        if ($delta === 0) {
            throw new InvalidArgumentException('A stock adjustment must change the quantity.');
        }

        return DB::transaction(function () use ($branchId, $batch, $delta, $type, $source, $reference, $notes, $unitCost): StockMovement {
            if ($delta < 0) {
                $held = $this->balanceQuery($branchId, $batch->id)->lockForUpdate()->value('quantity') ?? 0;

                if ($held + $delta < 0) {
                    $batch->loadMissing('product');

                    throw new InsufficientStockException($batch->product_id, $batch->product->displayName(), -$delta, (int) $held);
                }
            }

            return $this->post($branchId, $batch, $delta, $type, $unitCost ?? $batch->unit_cost, $source, $reference, $notes);
        });
    }

    /**
     * Sellable base units a branch holds of a product.
     */
    public function sellableOnHand(int $branchId, int $productId): int
    {
        return (int) $this->sellableBalances($branchId, $productId)->sum('stock_balances.quantity');
    }

    /**
     * All base units a branch holds of a product, sellable or not.
     */
    public function onHand(int $branchId, int $productId): int
    {
        return (int) StockBalance::withoutGlobalScope(BranchScope::class)
            ->where('branch_id', $branchId)
            ->where('product_id', $productId)
            ->sum('quantity');
    }

    /**
     * Balances a sale may draw from, earliest expiry first.
     *
     * @return Builder<StockBalance>
     */
    private function sellableBalances(int $branchId, int $productId): Builder
    {
        return StockBalance::withoutGlobalScope(BranchScope::class)
            ->select('stock_balances.*')
            ->join('batches', 'batches.id', '=', 'stock_balances.batch_id')
            ->where('stock_balances.branch_id', $branchId)
            ->where('stock_balances.product_id', $productId)
            ->where('stock_balances.quantity', '>', 0)
            ->where('batches.status', BatchStatus::Active->value)
            ->whereDate('batches.expires_on', '>=', today())
            ->orderBy('batches.expires_on')
            ->orderBy('batches.id');
    }

    /**
     * Writes one movement and applies it to the branch's balance row.
     */
    private function post(int $branchId, Batch $batch, int $quantity, StockMovementType $type, string $unitCost, ?Model $source, ?string $reference, ?string $notes): StockMovement
    {
        $balance = $this->balanceQuery($branchId, $batch->id)->lockForUpdate()->first();

        if ($balance === null) {
            $balance = new StockBalance(['branch_id' => $branchId, 'batch_id' => $batch->id, 'product_id' => $batch->product_id, 'quantity' => 0]);
        }

        $balance->quantity += $quantity;
        $balance->save();

        return StockMovement::query()->create([
            'branch_id' => $branchId,
            'batch_id' => $batch->id,
            'product_id' => $batch->product_id,
            'quantity' => $quantity,
            'type' => $type,
            'unit_cost' => $unitCost,
            'source_type' => $source?->getMorphClass(),
            'source_id' => $source?->getKey(),
            'reference' => $reference,
            'notes' => $notes,
            'user_id' => Auth::id(),
            'created_at' => now(),
        ]);
    }

    /**
     * @return Builder<StockBalance>
     */
    private function balanceQuery(int $branchId, int $batchId): Builder
    {
        return StockBalance::withoutGlobalScope(BranchScope::class)
            ->where('branch_id', $branchId)
            ->where('batch_id', $batchId);
    }

    private function totalOnHand(int $productId): int
    {
        return (int) StockBalance::withoutGlobalScope(BranchScope::class)->where('product_id', $productId)->sum('quantity');
    }

    /**
     * (old quantity × old cost + new quantity × new cost) ÷ total quantity.
     */
    private function weightedAverage(int $oldQuantity, string $oldCost, int $newQuantity, string $newCost): string
    {
        if ($oldQuantity <= 0) {
            return Decimal::round($newCost, Decimal::COST);
        }

        $value = Decimal::add(
            Decimal::mul($oldQuantity, $oldCost, 10),
            Decimal::mul($newQuantity, $newCost, 10),
            10,
        );

        return Decimal::div($value, $oldQuantity + $newQuantity, Decimal::COST);
    }

    private function assertPositive(int $quantity): void
    {
        if ($quantity <= 0) {
            throw new InvalidArgumentException('Quantity must be a positive number of base units.');
        }
    }
}
