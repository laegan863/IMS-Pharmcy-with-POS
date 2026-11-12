<?php

namespace App\Services\Purchasing;

use App\Enums\DocumentStatus;
use App\Enums\StockMovementType;
use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptLine;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\Supplier;
use App\Models\User;
use App\Services\DocumentNumbers;
use App\Services\Stock\StockService;
use App\Settings\Settings;
use App\Support\Decimal;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Goods receipts. Every line needs a lot number and an expiry date. Posting
 * adds the stock through StockService, updates costs, marks what was
 * received on the purchase order and takes the receipt number, all in one
 * transaction.
 *
 * Free goods add stock at no cost, so they lower the cost per base unit:
 * cost per base = paid quantity × unit cost ÷ ((paid + free) × conversion).
 */
class GoodsReceiptService
{
    public function __construct(
        private StockService $stock,
        private DocumentNumbers $numbers,
        private PurchaseOrderService $orders,
        private Settings $settings,
    ) {}

    /**
     * @param  array{supplier_id: int, purchase_order_id?: int|null, supplier_invoice_number?: string|null, supplier_invoice_date?: string|null, notes?: string|null}  $header
     * @param  list<array{purchase_order_line_id?: int|null, product_id: int, product_unit_id: int, lot_number: string, expires_on: string, quantity: int, free_quantity?: int, unit_cost: string}>  $lines
     */
    public function saveDraft(?GoodsReceipt $receipt, int $branchId, array $header, array $lines, User $user): GoodsReceipt
    {
        if ($receipt?->isPosted()) {
            throw ValidationException::withMessages(['status' => __('A posted receipt cannot be edited.')]);
        }

        $order = isset($header['purchase_order_id']) ? PurchaseOrder::query()->findOrFail($header['purchase_order_id']) : null;

        if ($order !== null && ! $order->status->isReceivable()) {
            throw ValidationException::withMessages(['purchase_order_id' => __('This order is not approved or is already fully received.')]);
        }

        if ($order !== null && $order->supplier_id !== $header['supplier_id']) {
            throw ValidationException::withMessages(['supplier_id' => __('The supplier must match the purchase order.')]);
        }

        return DB::transaction(function () use ($receipt, $branchId, $header, $lines, $user, $order): GoodsReceipt {
            $receipt ??= new GoodsReceipt(['branch_id' => $branchId, 'status' => DocumentStatus::Draft, 'created_by' => $user->id]);
            $receipt->fill([
                'supplier_id' => $header['supplier_id'],
                'purchase_order_id' => $order?->id,
                'supplier_invoice_number' => $header['supplier_invoice_number'] ?? null,
                'supplier_invoice_date' => $header['supplier_invoice_date'] ?? null,
                'notes' => $header['notes'] ?? null,
            ])->save();

            $receipt->lines()->delete();
            $resolver = new LineResolver($lines);
            $orderLines = $order?->lines()->get()->keyBy('id');
            $totals = [];

            foreach ($lines as $index => $line) {
                [$product, $unit] = $resolver->resolve($index, $line['product_id'], $line['product_unit_id']);
                $orderLineId = $line['purchase_order_line_id'] ?? null;

                if ($orderLineId !== null && ($orderLines === null || ! $orderLines->has($orderLineId) || $orderLines->get($orderLineId)?->product_id !== $product->id)) {
                    throw ValidationException::withMessages(["lines.{$index}.product_id" => __('This line does not match the purchase order.')]);
                }

                $lineTotal = Decimal::mul($line['quantity'], $line['unit_cost']);
                $totals[] = $lineTotal;

                $receipt->lines()->create([
                    'purchase_order_line_id' => $orderLineId,
                    'product_id' => $product->id,
                    'product_unit_id' => $unit->id,
                    'lot_number' => trim($line['lot_number']),
                    'expires_on' => $line['expires_on'],
                    'quantity' => $line['quantity'],
                    'free_quantity' => $line['free_quantity'] ?? 0,
                    'unit_cost' => Decimal::round($line['unit_cost'], Decimal::COST),
                    'line_total' => $lineTotal,
                ]);
            }

            $receipt->total = Decimal::sum($totals);
            $receipt->save();

            return $receipt->refresh();
        });
    }

    /**
     * Things the receiver should look at before posting. They do not block
     * posting once acknowledged.
     *
     * @return list<string>
     */
    public function warnings(GoodsReceipt $receipt): array
    {
        $receipt->loadMissing(['lines.product.genericName', 'lines.product.dosageForm', 'lines.unit', 'supplier']);
        $warnings = [];
        $minimumExpiry = CarbonImmutable::today()->addMonths($this->settings->int('stock.min_shelf_life_months'));

        if ($receipt->supplier->fdaLicenseExpired()) {
            $warnings[] = __('The FDA licence of :supplier has expired.', ['supplier' => $receipt->supplier->name]);
        }

        foreach ($receipt->lines as $line) {
            $name = $line->product->displayName();

            if ($line->expires_on->lt($minimumExpiry)) {
                $warnings[] = __(':product lot :lot expires on :date, under the minimum shelf life of :months months.', [
                    'product' => $name,
                    'lot' => $line->lot_number,
                    'date' => $line->expires_on->toDateString(),
                    'months' => $this->settings->int('stock.min_shelf_life_months'),
                ]);
            }

            $lastCost = $this->lastCostPerBase($line->product_id, $receipt->id);
            $newCost = $this->costPerBase($line);

            if ($lastCost !== null && Decimal::compare($lastCost, $newCost) !== 0) {
                $warnings[] = __(':product costs :new per :base, last time :old.', [
                    'product' => $name,
                    'new' => Decimal::round($newCost),
                    'old' => Decimal::round($lastCost),
                    'base' => mb_strtolower($line->product->units()->where('is_base', true)->value('name') ?? 'unit'),
                ]);
            }
        }

        return $warnings;
    }

    /**
     * Posts the receipt: stock in, costs updated, order progress recorded.
     *
     * @throws ValidationException when there are errors, or warnings not yet acknowledged
     */
    public function post(GoodsReceipt $receipt, User $user, bool $acknowledgeWarnings): GoodsReceipt
    {
        if ($receipt->isPosted()) {
            throw ValidationException::withMessages(['status' => __('This receipt is already posted.')]);
        }

        $receipt->load(['lines.unit', 'lines.product', 'lines.purchaseOrderLine']);

        if ($receipt->lines->isEmpty()) {
            throw ValidationException::withMessages(['lines' => __('Add at least one line before posting.')]);
        }

        foreach ($receipt->lines as $index => $line) {
            if ($line->expires_on->lt(CarbonImmutable::today())) {
                throw ValidationException::withMessages(["lines.{$index}.expires_on" => __('Expired stock cannot be received.')]);
            }
        }

        $warnings = $this->warnings($receipt);

        if ($warnings !== [] && ! $acknowledgeWarnings) {
            throw ValidationException::withMessages(['warnings' => $warnings]);
        }

        return DB::transaction(function () use ($receipt, $user): GoodsReceipt {
            $receipt = GoodsReceipt::query()->lockForUpdate()->findOrFail($receipt->id);

            if ($receipt->isPosted()) {
                throw ValidationException::withMessages(['status' => __('This receipt is already posted.')]);
            }

            $receipt->load(['lines.unit', 'lines.product']);
            $receipt->number = $this->numbers->next(DocumentNumbers::GOODS_RECEIPT, $receipt->branch_id);

            foreach ($receipt->lines as $index => $line) {
                $movement = $this->stock->receive(
                    $receipt->branch_id,
                    $line->product,
                    $line->lot_number,
                    $line->expires_on,
                    $line->totalBase(),
                    $this->costPerBase($line),
                    StockMovementType::Receipt,
                    $receipt,
                    $receipt->number,
                    $receipt->supplier_id,
                );

                $line->update(['batch_id' => $movement->batch_id]);

                if ($line->purchase_order_line_id !== null) {
                    $orderLine = PurchaseOrderLine::query()->lockForUpdate()->findOrFail($line->purchase_order_line_id);
                    $paidBase = $line->unit->toBase($line->quantity);

                    if ($paidBase > $orderLine->outstandingBase()) {
                        throw ValidationException::withMessages(["lines.{$index}.quantity" => __('More than is still outstanding on the purchase order.')]);
                    }

                    $orderLine->increment('received_base', $paidBase);
                }
            }

            $receipt->fill([
                'status' => DocumentStatus::Posted,
                'posted_by' => $user->id,
                'posted_at' => now(),
            ])->save();

            if ($receipt->purchase_order_id !== null) {
                $this->orders->refreshReceivedStatus(PurchaseOrder::query()->findOrFail($receipt->purchase_order_id));
            }

            Supplier::query()->whereKey($receipt->supplier_id)->touch();

            return $receipt->refresh();
        });
    }

    public function costPerBase(GoodsReceiptLine $line): string
    {
        $base = $line->totalBase();

        return $base === 0 ? '0' : Decimal::div(Decimal::mul($line->quantity, $line->unit_cost, 4), $base, Decimal::COST);
    }

    /**
     * Cost per base unit on the product's most recent posted receipt.
     */
    private function lastCostPerBase(int $productId, int $exceptReceiptId): ?string
    {
        $last = GoodsReceiptLine::query()
            ->with('unit')
            ->where('product_id', $productId)
            ->where('goods_receipt_id', '<>', $exceptReceiptId)
            ->whereHas('goodsReceipt', fn ($query) => $query->where('status', DocumentStatus::Posted->value))
            ->latest('id')
            ->first();

        return $last === null ? null : $this->costPerBase($last);
    }
}
