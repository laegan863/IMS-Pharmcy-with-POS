<?php

namespace App\Services\Purchasing;

use App\Enums\PurchaseOrderStatus;
use App\Models\PurchaseOrder;
use App\Models\User;
use App\Services\DocumentNumbers;
use App\Support\Decimal;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Purchase orders: drafted, approved by a pharmacist or owner, then received
 * in one or more goods receipts. Orders never move stock.
 */
class PurchaseOrderService
{
    public function __construct(private DocumentNumbers $numbers) {}

    /**
     * Creates or replaces a draft order.
     *
     * @param  array{supplier_id: int, expected_on?: string|null, notes?: string|null}  $header
     * @param  list<array{product_id: int, product_unit_id: int, quantity: int, unit_cost: string}>  $lines
     */
    public function saveDraft(?PurchaseOrder $order, int $branchId, array $header, array $lines, User $user): PurchaseOrder
    {
        if ($order !== null && $order->status !== PurchaseOrderStatus::Draft) {
            throw ValidationException::withMessages(['status' => __('Only draft orders can be edited.')]);
        }

        return DB::transaction(function () use ($order, $branchId, $header, $lines, $user): PurchaseOrder {
            if ($order === null) {
                $order = new PurchaseOrder([
                    'branch_id' => $branchId,
                    'number' => $this->numbers->next(DocumentNumbers::PURCHASE_ORDER, $branchId),
                    'status' => PurchaseOrderStatus::Draft,
                    'created_by' => $user->id,
                ]);
            }

            $order->fill([
                'supplier_id' => $header['supplier_id'],
                'expected_on' => $header['expected_on'] ?? null,
                'notes' => $header['notes'] ?? null,
            ])->save();

            $order->lines()->delete();
            $resolver = new LineResolver($lines);
            $totals = [];

            foreach ($lines as $index => $line) {
                [$product, $unit] = $resolver->resolve($index, $line['product_id'], $line['product_unit_id']);
                $lineTotal = Decimal::mul($line['quantity'], $line['unit_cost']);
                $totals[] = $lineTotal;

                $order->lines()->create([
                    'product_id' => $product->id,
                    'product_unit_id' => $unit->id,
                    'quantity' => $line['quantity'],
                    'quantity_base' => $unit->toBase($line['quantity']),
                    'unit_cost' => Decimal::round($line['unit_cost'], Decimal::COST),
                    'line_total' => $lineTotal,
                ]);
            }

            $order->total = Decimal::sum($totals);
            $order->save();

            return $order->refresh();
        });
    }

    public function approve(PurchaseOrder $order, User $approver): void
    {
        if ($order->status !== PurchaseOrderStatus::Draft) {
            throw ValidationException::withMessages(['status' => __('Only draft orders can be approved.')]);
        }

        if (! $order->lines()->exists()) {
            throw ValidationException::withMessages(['lines' => __('Add at least one line before approving.')]);
        }

        $order->update([
            'status' => PurchaseOrderStatus::Approved,
            'approved_by' => $approver->id,
            'approved_at' => now(),
        ]);
    }

    public function cancel(PurchaseOrder $order): void
    {
        if (! in_array($order->status, [PurchaseOrderStatus::Draft, PurchaseOrderStatus::Approved], true)) {
            throw ValidationException::withMessages(['status' => __('Orders with goods received cannot be cancelled.')]);
        }

        $order->update(['status' => PurchaseOrderStatus::Cancelled]);
    }

    /**
     * Recomputes the order's status from what has been received.
     */
    public function refreshReceivedStatus(PurchaseOrder $order): void
    {
        $order->load('lines');
        $received = $order->lines->sum('received_base');
        $outstanding = $order->lines->sum(fn ($line) => $line->outstandingBase());

        $order->update([
            'status' => match (true) {
                $outstanding === 0 => PurchaseOrderStatus::Received,
                $received > 0 => PurchaseOrderStatus::PartlyReceived,
                default => $order->status,
            },
        ]);
    }
}
