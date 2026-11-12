<?php

namespace App\Http\Controllers\Stock;

use App\Enums\Permission;
use App\Exports\OpeningStockTemplate;
use App\Http\Controllers\Controller;
use App\Imports\HeadingRowSheet;
use App\Models\Batch;
use App\Models\Category;
use App\Models\Product;
use App\Models\SaleLineBatch;
use App\Models\StockBalance;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\Stock\OpeningStockImporter;
use App\Services\Stock\StockControlService;
use App\Settings\Settings;
use App\Support\Decimal;
use App\Support\TableQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Stock on hand for the user's branch, per product and per batch, and the
 * full movement history of any batch. Read-only: stock only changes through
 * documents that call StockService.
 */
class StockController extends Controller
{
    public function index(Request $request, Settings $settings): Response
    {
        $branchId = $this->branchId($request);
        $alertDays = (int) max([90, ...(array) $settings->get('stock.expiry_alert_days')]);

        // Correlated subqueries per product, for this branch only.
        $onHand = fn (QueryBuilder $query) => $query
            ->selectRaw('COALESCE(SUM(stock_balances.quantity), 0)')
            ->from('stock_balances')
            ->whereColumn('stock_balances.product_id', 'products.id')
            ->where('stock_balances.branch_id', $branchId);

        $sellable = fn (QueryBuilder $query) => $query
            ->selectRaw('COALESCE(SUM(stock_balances.quantity), 0)')
            ->from('stock_balances')
            ->join('batches', 'batches.id', '=', 'stock_balances.batch_id')
            ->whereColumn('stock_balances.product_id', 'products.id')
            ->where('stock_balances.branch_id', $branchId)
            ->where('batches.status', 'active')
            ->whereDate('batches.expires_on', '>=', today());

        $nearestExpiry = fn (QueryBuilder $query) => $query
            ->selectRaw('MIN(batches.expires_on)')
            ->from('stock_balances')
            ->join('batches', 'batches.id', '=', 'stock_balances.batch_id')
            ->whereColumn('stock_balances.product_id', 'products.id')
            ->where('stock_balances.branch_id', $branchId)
            ->where('stock_balances.quantity', '>', 0);

        $query = Product::query()
            ->with(['genericName:id,name', 'dosageForm:id,name', 'baseUnit'])
            ->select('products.*')
            ->selectSub($onHand, 'on_hand')
            ->selectSub($sellable, 'sellable')
            ->selectSub($nearestExpiry, 'nearest_expiry');

        $table = TableQuery::for($query, $request)
            ->searchUsing(fn (Builder $query, string $term) => $query->search($term))
            ->sortable([
                'sku' => 'products.sku',
                'on_hand' => fn (Builder $query, string $direction) => $query->orderBy('on_hand', $direction === 'desc' ? 'desc' : 'asc'),
                'nearest_expiry' => fn (Builder $query, string $direction) => $query->orderBy('nearest_expiry', $direction === 'desc' ? 'desc' : 'asc'),
            ])
            ->defaultSort('sku')
            ->filter('category', fn (Builder $query, string $value) => $query->where('category_id', (int) $value))
            ->filter('view', fn (Builder $query, string $value) => match ($value) {
                'in_stock' => $query->where($onHand, '>', 0),
                'low' => $query->where($sellable, '<=', DB::raw('products.reorder_point')),
                'near_expiry' => $query->where($nearestExpiry, '<=', today()->addDays($alertDays)->toDateString()),
                default => $query,
            })
            ->paginate(fn (Product $product) => [
                'id' => $product->id,
                'sku' => $product->sku,
                'name' => $product->displayName(),
                'base_unit' => $product->baseUnit?->name,
                'on_hand' => (int) $product->getAttribute('on_hand'),
                'sellable' => (int) $product->getAttribute('sellable'),
                'reorder_point' => $product->reorder_point,
                'nearest_expiry' => $product->getAttribute('nearest_expiry') ? substr((string) $product->getAttribute('nearest_expiry'), 0, 10) : null,
                'value' => Decimal::mul((int) $product->getAttribute('on_hand'), $product->average_cost),
            ]);

        return Inertia::render('stock/index', [
            ...$table,
            'categories' => Category::options(),
            'alertDays' => $alertDays,
        ]);
    }

    public function product(Request $request, Product $product): Response
    {
        $branchId = $this->branchId($request);
        $product->load(['genericName', 'dosageForm', 'baseUnit']);

        $batches = StockBalance::query()
            ->with('batch.supplier:id,name')
            ->where('branch_id', $branchId)
            ->where('product_id', $product->id)
            ->where('quantity', '>', 0)
            ->get()
            ->sortBy(fn (StockBalance $balance) => $balance->batch->expires_on)
            ->values()
            ->map(fn (StockBalance $balance) => [
                'batch_id' => $balance->batch_id,
                'lot_number' => $balance->batch->lot_number,
                'expires_on' => $balance->batch->expires_on->toDateString(),
                'days_to_expiry' => $balance->batch->daysToExpiry(),
                'status' => $balance->batch->status->value,
                'sellable' => $balance->batch->isSellable(),
                'quantity' => $balance->quantity,
                'unit_cost' => $balance->batch->unit_cost,
                'supplier' => $balance->batch->supplier?->name,
            ]);

        return Inertia::render('stock/product', [
            'product' => [
                'id' => $product->id,
                'sku' => $product->sku,
                'name' => $product->displayName(),
                'base_unit' => $product->baseUnit?->name,
                'average_cost' => $product->average_cost,
                'reorder_point' => $product->reorder_point,
            ],
            'batches' => $batches,
        ]);
    }

    public function batch(Request $request, Batch $batch): Response
    {
        $branchId = $this->branchId($request);
        $batch->load(['product.genericName', 'product.dosageForm', 'product.baseUnit', 'supplier']);

        $movements = StockMovement::query()
            ->with('user:id,name')
            ->where('batch_id', $batch->id)
            ->where('branch_id', $branchId)
            ->orderBy('id')
            ->get();

        $running = 0;

        return Inertia::render('stock/batch', [
            'batch' => [
                'id' => $batch->id,
                'lot_number' => $batch->lot_number,
                'expires_on' => $batch->expires_on->toDateString(),
                'status' => $batch->status->value,
                'status_reason' => $batch->status_reason,
                'unit_cost' => $batch->unit_cost,
                'supplier' => $batch->supplier?->name,
                'product' => ['id' => $batch->product_id, 'name' => $batch->product->displayName(), 'base_unit' => $batch->product->baseUnit?->name],
            ],
            'movements' => $movements->map(function (StockMovement $movement) use (&$running) {
                $running += $movement->quantity;

                return [
                    'id' => $movement->id,
                    'at' => $movement->created_at->toIso8601String(),
                    'type' => $movement->type->label(),
                    'quantity' => $movement->quantity,
                    'balance' => $running,
                    'reference' => $movement->reference,
                    'notes' => $movement->notes,
                    'user' => $movement->user?->name,
                ];
            })->reverse()->values(),
            // Recall trace: every invoice in any branch that took units of this batch.
            'soldTo' => SaleLineBatch::query()
                ->with(['saleLine.sale.customer:id,name,phone'])
                ->where('batch_id', $batch->id)
                ->latest('id')
                ->limit(500)
                ->get()
                ->map(fn (SaleLineBatch $line) => [
                    'invoice' => $line->saleLine->sale->number,
                    'sale_id' => $line->saleLine->sale_id,
                    'date' => $line->saleLine->sale->completed_at->toIso8601String(),
                    'quantity' => $line->quantity_base - $line->returned_base,
                    'customer' => $line->saleLine->sale->customer !== null ? $line->saleLine->sale->customer->name : $line->saleLine->sale->discount_holder_name,
                    'phone' => $line->saleLine->sale->customer?->phone,
                ])
                ->values(),
            'canQuarantine' => $request->user()?->can(Permission::StockQuarantine->value) ?? false,
        ]);
    }

    public function quarantine(Request $request, Batch $batch, StockControlService $control): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:255']]);
        $control->quarantine($batch, $data['reason']);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Lot :lot quarantined in every branch.', ['lot' => $batch->lot_number])]);

        return back();
    }

    public function release(Batch $batch, StockControlService $control): RedirectResponse
    {
        $control->release($batch);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Lot :lot released.', ['lot' => $batch->lot_number])]);

        return back();
    }

    /**
     * Batches of a product this branch holds, for document line pickers.
     */
    public function batchLookup(Request $request): JsonResponse
    {
        return response()->json([
            'data' => StockBalance::query()
                ->with('batch')
                ->where('branch_id', $this->branchId($request))
                ->where('product_id', $request->integer('product_id'))
                ->where('quantity', '>', 0)
                ->get()
                ->map(fn (StockBalance $balance) => [
                    'batch_id' => $balance->batch_id,
                    'lot_number' => $balance->batch->lot_number,
                    'expires_on' => $balance->batch->expires_on->toDateString(),
                    'status' => $balance->batch->status->value,
                    'quantity' => $balance->quantity,
                    'unit_cost' => $balance->batch->unit_cost,
                ])
                ->sortBy('expires_on')
                ->values(),
        ]);
    }

    public function openingCreate(): Response
    {
        return Inertia::render('stock/opening-import', [
            'columns' => OpeningStockImporter::COLUMNS,
            'report' => null,
        ]);
    }

    public function openingTemplate(): BinaryFileResponse
    {
        return Excel::download(new OpeningStockTemplate, 'opening-stock-template.xlsx');
    }

    public function openingStore(Request $request, OpeningStockImporter $importer): Response
    {
        $request->validate(['file' => ['required', 'file', 'mimes:xlsx,xls,csv,txt', 'max:10240']]);

        $rows = array_values(Excel::toArray(new HeadingRowSheet, $request->file('file'))[0] ?? []);
        $report = $importer->import($this->branchId($request), $rows);

        activity('import')
            ->event('opening-stock-imported')
            ->withProperties(['batches' => $report['batches'], 'units' => $report['units'], 'errors' => count($report['errors'])])
            ->log('Opening stock import');

        return Inertia::render('stock/opening-import', [
            'columns' => OpeningStockImporter::COLUMNS,
            'report' => $report,
        ]);
    }

    private function branchId(Request $request): int
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->branch_id !== null, 403, __('Your account has no branch.'));

        return $user->branch_id;
    }
}
