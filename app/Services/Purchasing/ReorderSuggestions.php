<?php

namespace App\Services\Purchasing;

use App\Enums\StockMovementType;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Services\Stock\StockService;
use App\Support\Decimal;
use Illuminate\Support\Facades\DB;

/**
 * Products at or below their reorder point, with a suggested order.
 *
 * Suggested quantity: enough to reach the maximum level, or if none is set,
 * 30 days of sales at the last 30 days' rate (at least twice the reorder
 * point), less what is on hand and already on open orders, rounded up to
 * whole purchase units.
 */
class ReorderSuggestions
{
    private const VELOCITY_DAYS = 30;

    public function __construct(private StockService $stock) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function forBranch(int $branchId): array
    {
        $since = now()->subDays(self::VELOCITY_DAYS);

        $sold = DB::table('stock_movements')
            ->where('branch_id', $branchId)
            ->where('type', StockMovementType::Sale->value)
            ->where('created_at', '>=', $since)
            ->groupBy('product_id')
            ->pluck(DB::raw('-SUM(quantity)'), 'product_id');

        $onOrder = DB::table('purchase_order_lines')
            ->join('purchase_orders', 'purchase_orders.id', '=', 'purchase_order_lines.purchase_order_id')
            ->where('purchase_orders.branch_id', $branchId)
            ->whereIn('purchase_orders.status', ['draft', 'approved', 'partly_received'])
            ->groupBy('purchase_order_lines.product_id')
            ->pluck(DB::raw('SUM(purchase_order_lines.quantity_base - purchase_order_lines.received_base)'), 'purchase_order_lines.product_id');

        $suggestions = [];

        Product::query()
            ->with(['units', 'genericName', 'dosageForm'])
            ->where('is_active', true)
            ->where('reorder_point', '>', 0)
            ->orderBy('sku')
            ->each(function (Product $product) use ($branchId, $sold, $onOrder, &$suggestions): void {
                $onHand = $this->stock->sellableOnHand($branchId, $product->id);

                if ($onHand > $product->reorder_point) {
                    return;
                }

                $velocity = (int) ($sold[$product->id] ?? 0);
                $target = $product->max_level ?? max($product->reorder_point * 2, $velocity);
                $needed = max(0, $target - $onHand - (int) ($onOrder[$product->id] ?? 0));

                if ($needed === 0) {
                    return;
                }

                /** @var ProductUnit|null $unit */
                $unit = $product->units->where('is_purchasable', true)->sortByDesc('conversion_factor')->first()
                    ?? $product->units->firstWhere('is_base', true);

                if ($unit === null) {
                    return;
                }

                $quantity = (int) ceil($needed / $unit->conversion_factor);

                $suggestions[] = [
                    'product_id' => $product->id,
                    'sku' => $product->sku,
                    'name' => $product->displayName(),
                    'on_hand' => $onHand,
                    'on_order' => (int) ($onOrder[$product->id] ?? 0),
                    'reorder_point' => $product->reorder_point,
                    'sold_30_days' => $velocity,
                    'product_unit_id' => $unit->id,
                    'unit' => $unit->name,
                    'conversion_factor' => $unit->conversion_factor,
                    'quantity' => $quantity,
                    'unit_cost' => $this->lastUnitCost($product, $unit),
                ];
            });

        return $suggestions;
    }

    /**
     * The last price paid for this unit, or the average cost scaled up.
     */
    private function lastUnitCost(Product $product, ProductUnit $unit): string
    {
        $last = DB::table('goods_receipt_lines')
            ->join('goods_receipts', 'goods_receipts.id', '=', 'goods_receipt_lines.goods_receipt_id')
            ->where('goods_receipts.status', 'posted')
            ->where('goods_receipt_lines.product_unit_id', $unit->id)
            ->latest('goods_receipt_lines.id')
            ->value('goods_receipt_lines.unit_cost');

        return $last !== null ? (string) $last : Decimal::mul($product->average_cost, $unit->conversion_factor, 4);
    }
}
