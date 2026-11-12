<?php

namespace App\Services\Purchasing;

use App\Enums\DocumentStatus;
use App\Enums\StockMovementType;
use App\Models\Batch;
use App\Models\SupplierReturn;
use App\Models\User;
use App\Services\DocumentNumbers;
use App\Services\Stock\InsufficientStockException;
use App\Services\Stock\StockService;
use App\Support\Decimal;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Returns to a supplier, from named batches. Posting takes the stock out of
 * those batches (expired and quarantined batches included, since they are
 * the usual reason for a return).
 */
class SupplierReturnService
{
    public function __construct(private StockService $stock, private DocumentNumbers $numbers) {}

    /**
     * @param  array{supplier_id: int, reason: string}  $header
     * @param  list<array{batch_id: int, quantity_base: int}>  $lines
     */
    public function saveDraft(?SupplierReturn $return, int $branchId, array $header, array $lines, User $user): SupplierReturn
    {
        if ($return !== null && $return->status === DocumentStatus::Posted) {
            throw ValidationException::withMessages(['status' => __('A posted return cannot be edited.')]);
        }

        return DB::transaction(function () use ($return, $branchId, $header, $lines, $user): SupplierReturn {
            $return ??= new SupplierReturn(['branch_id' => $branchId, 'status' => DocumentStatus::Draft, 'created_by' => $user->id]);
            $return->fill($header)->save();
            $return->lines()->delete();

            $batches = Batch::query()->whereIn('id', array_column($lines, 'batch_id'))->get()->keyBy('id');
            $totals = [];

            foreach ($lines as $index => $line) {
                $batch = $batches->get($line['batch_id']);

                if ($batch === null) {
                    throw ValidationException::withMessages(["lines.{$index}.batch_id" => __('Choose a batch.')]);
                }

                $lineTotal = Decimal::mul($line['quantity_base'], $batch->unit_cost);
                $totals[] = $lineTotal;

                $return->lines()->create([
                    'batch_id' => $batch->id,
                    'product_id' => $batch->product_id,
                    'quantity_base' => $line['quantity_base'],
                    'unit_cost' => $batch->unit_cost,
                    'line_total' => $lineTotal,
                ]);
            }

            $return->total = Decimal::sum($totals);
            $return->save();

            return $return->refresh();
        });
    }

    public function post(SupplierReturn $return, User $user): SupplierReturn
    {
        return DB::transaction(function () use ($return, $user): SupplierReturn {
            $return = SupplierReturn::query()->lockForUpdate()->findOrFail($return->id);

            if ($return->status === DocumentStatus::Posted) {
                throw ValidationException::withMessages(['status' => __('This return is already posted.')]);
            }

            $return->load('lines.batch');

            if ($return->lines->isEmpty()) {
                throw ValidationException::withMessages(['lines' => __('Add at least one line before posting.')]);
            }

            $return->number = $this->numbers->next(DocumentNumbers::SUPPLIER_RETURN, $return->branch_id);

            foreach ($return->lines as $index => $line) {
                try {
                    $this->stock->adjust($return->branch_id, $line->batch, -$line->quantity_base, StockMovementType::SupplierReturn, $return, $return->number, $return->reason);
                } catch (InsufficientStockException $exception) {
                    throw ValidationException::withMessages(["lines.{$index}.quantity_base" => __('Only :available on hand in this batch.', ['available' => $exception->available])]);
                }
            }

            $return->fill(['status' => DocumentStatus::Posted, 'posted_by' => $user->id, 'posted_at' => now()])->save();

            return $return->refresh();
        });
    }
}
