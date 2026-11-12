<?php

namespace App\Services;

use App\Enums\Permission;
use App\Models\User;
use App\Support\Decimal;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * The figures on the dashboard for one branch: today's sales, stock that
 * needs attention, and documents waiting for the user. Cashiers see only
 * their own sales.
 */
class DashboardService
{
    /**
     * @return array<string, mixed>
     */
    public function forUser(User $user): array
    {
        $branchId = (int) $user->branch_id;
        $today = CarbonImmutable::today();
        $full = $user->can(Permission::DashboardView->value);

        $sales = DB::table('sales')
            ->where('branch_id', $branchId)
            ->where('status', '<>', 'voided')
            ->whereBetween('completed_at', [$today->startOfDay(), $today->endOfDay()])
            ->when(! $full, fn (Builder $query) => $query->where('user_id', $user->id));

        $data = [
            'scope' => $full ? 'branch' : 'mine',
            'today' => [
                'invoices' => (clone $sales)->count(),
                'net' => Decimal::round((string) (clone $sales)->sum('total')),
                'discounts' => Decimal::round((string) (clone $sales)->sum('statutory_discount')),
                'by_hour' => $this->byHour(clone $sales),
            ],
            'week' => $full ? $this->lastSevenDays($branchId) : [],
        ];

        if (! $user->can(Permission::StockView->value) || ! $full) {
            return $data;
        }

        $batches = DB::table('stock_balances')
            ->join('batches', 'batches.id', '=', 'stock_balances.batch_id')
            ->join('products', 'products.id', '=', 'batches.product_id')
            ->join('generic_names', 'generic_names.id', '=', 'products.generic_name_id')
            ->where('stock_balances.branch_id', $branchId)
            ->where('stock_balances.quantity', '>', 0);

        $data['near_expiry'] = (clone $batches)
            ->whereDate('batches.expires_on', '>=', $today->toDateString())
            ->whereDate('batches.expires_on', '<=', $today->addDays(90)->toDateString())
            ->orderBy('batches.expires_on')
            ->limit(8)
            ->get(['batches.id', 'batches.lot_number', 'batches.expires_on', 'stock_balances.quantity', 'products.brand_name', 'generic_names.name as generic'])
            ->map(fn ($row) => [
                'batch_id' => $row->id,
                'name' => $row->brand_name ? "{$row->brand_name} ({$row->generic})" : $row->generic,
                'lot' => $row->lot_number,
                'expires_on' => substr((string) $row->expires_on, 0, 10),
                'quantity' => (int) $row->quantity,
            ]);

        $expired = (clone $batches)->whereDate('batches.expires_on', '<', $today->toDateString());
        $data['expired'] = [
            'batches' => (clone $expired)->count(),
            'value' => Decimal::round((string) (clone $expired)->sum(DB::raw('stock_balances.quantity * batches.unit_cost'))),
        ];

        // One grouped query for sellable stock rather than one per product.
        $sellable = DB::table('stock_balances')
            ->join('batches', 'batches.id', '=', 'stock_balances.batch_id')
            ->where('stock_balances.branch_id', $branchId)
            ->where('batches.status', 'active')
            ->whereDate('batches.expires_on', '>=', $today->toDateString())
            ->groupBy('stock_balances.product_id')
            ->selectRaw('stock_balances.product_id, SUM(stock_balances.quantity) as quantity');

        $data['low_stock'] = DB::table('products')
            ->join('generic_names', 'generic_names.id', '=', 'products.generic_name_id')
            ->leftJoinSub($sellable, 'sellable', 'sellable.product_id', '=', 'products.id')
            ->where('products.is_active', true)
            ->where('products.reorder_point', '>', 0)
            ->whereRaw('COALESCE(sellable.quantity, 0) <= products.reorder_point')
            ->orderByRaw('COALESCE(sellable.quantity, 0)')
            ->limit(8)
            ->get(['products.id', 'products.brand_name', 'products.reorder_point', 'generic_names.name as generic', DB::raw('COALESCE(sellable.quantity, 0) as on_hand')])
            ->map(fn ($row) => [
                'product_id' => (int) $row->id,
                'name' => $row->brand_name ? "{$row->brand_name} ({$row->generic})" : $row->generic,
                'on_hand' => (int) $row->on_hand,
                'reorder_point' => (int) $row->reorder_point,
            ]);

        $data['pending'] = array_filter([
            'purchase_orders_to_approve' => $user->can(Permission::PurchaseOrdersApprove->value)
                ? DB::table('purchase_orders')->where('branch_id', $branchId)->where('status', 'draft')->count() : null,
            'adjustments_to_post' => $user->can(Permission::StockAdjustApprove->value)
                ? DB::table('stock_adjustments')->where('branch_id', $branchId)->where('status', 'draft')->count() : null,
            'counts_to_post' => $user->can(Permission::StockCountApprove->value)
                ? DB::table('stock_counts')->where('branch_id', $branchId)->where('status', 'reviewed')->count() : null,
            'transfers_to_receive' => $user->can(Permission::StockTransfer->value)
                ? DB::table('stock_transfers')->where('to_branch_id', $branchId)->where('status', 'in_transit')->count() : null,
            'receipts_in_draft' => $user->can(Permission::ReceiptsManage->value)
                ? DB::table('goods_receipts')->where('branch_id', $branchId)->where('status', 'draft')->count() : null,
        ], fn ($count) => $count !== null);

        $data['notifications'] = $user->unreadNotifications()->limit(5)->get()->map(fn ($notification) => [
            'id' => $notification->id,
            'data' => $notification->data,
            'created_at' => $notification->created_at?->toIso8601String(),
        ]);

        return $data;
    }

    /**
     * @return list<array{hour: int, net: string}>
     */
    private function byHour(Builder $sales): array
    {
        $totals = [];

        foreach ($sales->get(['completed_at', 'total']) as $sale) {
            $hour = (int) CarbonImmutable::parse((string) $sale->completed_at)->timezone((string) config('app.timezone'))->format('G');
            $totals[$hour] = Decimal::add($totals[$hour] ?? '0', (string) $sale->total);
        }

        ksort($totals);

        return array_map(fn (int $hour, string $net) => ['hour' => $hour, 'net' => $net], array_keys($totals), $totals);
    }

    /**
     * @return list<array{day: string, net: string}>
     */
    private function lastSevenDays(int $branchId): array
    {
        $days = [];

        for ($i = 6; $i >= 0; $i--) {
            $day = CarbonImmutable::today()->subDays($i);
            $days[] = [
                'day' => $day->toDateString(),
                'net' => Decimal::round((string) DB::table('sales')
                    ->where('branch_id', $branchId)
                    ->where('status', '<>', 'voided')
                    ->whereBetween('completed_at', [$day->startOfDay(), $day->endOfDay()])
                    ->sum('total')),
            ];
        }

        return $days;
    }
}
