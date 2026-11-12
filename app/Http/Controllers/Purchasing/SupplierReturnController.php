<?php

namespace App\Http\Controllers\Purchasing;

use App\Http\Controllers\Controller;
use App\Models\StockBalance;
use App\Models\Supplier;
use App\Models\SupplierReturn;
use App\Models\SupplierReturnLine;
use App\Models\User;
use App\Services\Purchasing\SupplierReturnService;
use App\Support\TableQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class SupplierReturnController extends Controller
{
    public function index(Request $request): Response
    {
        $table = TableQuery::for(SupplierReturn::query()->with('supplier:id,name'), $request)
            ->searchUsing(fn (Builder $query, string $term) => $query
                ->where('number', 'like', "%{$term}%")
                ->orWhereHas('supplier', fn (Builder $query) => $query->where('name', 'like', "%{$term}%")))
            ->sortable(['number', 'created_at'])
            ->defaultSort('created_at', 'desc')
            ->paginate(fn (SupplierReturn $return) => [
                'id' => $return->id,
                'number' => $return->number,
                'supplier' => $return->supplier->name,
                'reason' => $return->reason,
                'status' => $return->status->value,
                'total' => $return->total,
                'created_at' => $return->created_at?->toIso8601String(),
            ]);

        return Inertia::render('purchasing/returns/index', $table);
    }

    /**
     * Batches of a product this branch holds, for choosing what to return.
     */
    public function batches(Request $request): JsonResponse
    {
        $productId = $request->integer('product_id');

        return response()->json([
            'data' => StockBalance::query()
                ->with('batch')
                ->where('product_id', $productId)
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

    public function create(): Response
    {
        return Inertia::render('purchasing/returns/form', ['supplierReturn' => null, 'suppliers' => $this->suppliers()]);
    }

    public function store(Request $request, SupplierReturnService $returns): RedirectResponse
    {
        [$header, $lines] = $this->validated($request);
        $return = $returns->saveDraft(null, $this->branchId($request), $header, $lines, $this->user($request));

        return $this->afterSave($request, $return, $returns);
    }

    public function show(SupplierReturn $return): Response
    {
        $return->load(['supplier', 'lines.batch', 'lines.product.genericName', 'lines.product.dosageForm']);

        return Inertia::render('purchasing/returns/form', [
            'supplierReturn' => [
                'id' => $return->id,
                'number' => $return->number,
                'status' => $return->status->value,
                'supplier_id' => $return->supplier_id,
                'reason' => $return->reason,
                'total' => $return->total,
                'lines' => $return->lines->map(fn (SupplierReturnLine $line) => [
                    'batch_id' => $line->batch_id,
                    'product_id' => $line->product_id,
                    'product_name' => $line->product->displayName(),
                    'lot_number' => $line->batch->lot_number,
                    'expires_on' => $line->batch->expires_on->toDateString(),
                    'quantity_base' => $line->quantity_base,
                    'unit_cost' => $line->unit_cost,
                ])->values(),
            ],
            'suppliers' => $this->suppliers(),
        ]);
    }

    public function update(Request $request, SupplierReturn $return, SupplierReturnService $returns): RedirectResponse
    {
        [$header, $lines] = $this->validated($request);
        $return = $returns->saveDraft($return, $return->branch_id, $header, $lines, $this->user($request));

        return $this->afterSave($request, $return, $returns);
    }

    private function afterSave(Request $request, SupplierReturn $return, SupplierReturnService $returns): RedirectResponse
    {
        if ($request->boolean('post')) {
            try {
                $return = $returns->post($return, $this->user($request));
            } catch (ValidationException $exception) {
                // The draft is saved; show it with what stopped the posting.
                return to_route('purchasing.returns.show', $return)->withErrors($exception->errors());
            }

            Inertia::flash('toast', ['type' => 'success', 'message' => __('Return :number posted. Stock updated.', ['number' => $return->number])]);
        } else {
            Inertia::flash('toast', ['type' => 'success', 'message' => __('Draft saved.')]);
        }

        return to_route('purchasing.returns.show', $return);
    }

    /**
     * @return array{0: array{supplier_id: int, reason: string}, 1: list<array{batch_id: int, quantity_base: int}>}
     */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'supplier_id' => ['required', 'integer', Rule::exists('suppliers', 'id')],
            'reason' => ['required', 'string', 'max:255'],
            'lines' => ['required', 'array', 'min:1', 'max:200'],
            'lines.*.batch_id' => ['required', 'integer', 'distinct'],
            'lines.*.quantity_base' => ['required', 'integer', 'min:1'],
        ]);

        return [
            ['supplier_id' => (int) $data['supplier_id'], 'reason' => (string) $data['reason']],
            array_values(array_map(fn (array $line) => ['batch_id' => (int) $line['batch_id'], 'quantity_base' => (int) $line['quantity_base']], $data['lines'])),
        ];
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function suppliers(): array
    {
        return array_values(Supplier::query()->orderBy('name')->get(['id', 'name'])
            ->map(fn (Supplier $supplier) => ['value' => (string) $supplier->id, 'label' => $supplier->name])->all());
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }

    private function branchId(Request $request): int
    {
        $branchId = $this->user($request)->branch_id;
        abort_if($branchId === null, 403, __('Your account has no branch.'));

        return $branchId;
    }
}
