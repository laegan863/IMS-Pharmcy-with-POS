<?php

namespace App\Http\Controllers\Stock;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\StockTransfer;
use App\Models\StockTransferLine;
use App\Models\User;
use App\Services\Stock\StockControlService;
use App\Support\TableQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class StockTransferController extends Controller
{
    public function index(Request $request): Response
    {
        $branchId = (int) $this->user($request)->branch_id;

        $table = TableQuery::for(StockTransfer::query()->involving($branchId)->with(['fromBranch:id,name', 'toBranch:id,name']), $request)
            ->searchUsing(fn (Builder $query, string $term) => $query->where('number', 'like', "%{$term}%"))
            ->sortable(['created_at'])
            ->defaultSort('created_at', 'desc')
            ->filter('status', fn (Builder $query, string $value) => $query->where('status', $value))
            ->paginate(fn (StockTransfer $transfer) => [
                'id' => $transfer->id,
                'number' => $transfer->number,
                'status' => $transfer->status,
                'from' => $transfer->fromBranch->name,
                'to' => $transfer->toBranch->name,
                'direction' => $transfer->branch_id === $branchId ? 'out' : 'in',
                'created_at' => $transfer->created_at?->toIso8601String(),
            ]);

        return Inertia::render('stock/transfers/index', $table);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('stock/transfers/form', ['transfer' => null, 'branches' => $this->branches($request)]);
    }

    public function store(Request $request, StockControlService $control): RedirectResponse
    {
        $data = $this->validated($request);
        $user = $this->user($request);
        $transfer = $control->saveTransfer(null, (int) $user->branch_id, $data['to_branch_id'], $data['note'], $data['lines'], $user);

        return $this->afterSave($request, $transfer, $control);
    }

    public function show(Request $request, StockTransfer $transfer): Response
    {
        $user = $this->user($request);
        abort_unless(in_array($user->branch_id, [$transfer->branch_id, $transfer->to_branch_id], true), 404);
        $transfer->load(['lines.batch', 'lines.product.genericName', 'lines.product.dosageForm', 'fromBranch', 'toBranch']);

        return Inertia::render('stock/transfers/form', [
            'transfer' => [
                'id' => $transfer->id,
                'number' => $transfer->number,
                'status' => $transfer->status,
                'from' => $transfer->fromBranch->name,
                'to_branch_id' => $transfer->to_branch_id,
                'to' => $transfer->toBranch->name,
                'note' => $transfer->note,
                'is_sender' => $user->branch_id === $transfer->branch_id,
                'is_receiver' => $user->branch_id === $transfer->to_branch_id,
                'dispatched_at' => $transfer->dispatched_at?->toIso8601String(),
                'received_at' => $transfer->received_at?->toIso8601String(),
                'lines' => $transfer->lines->map(fn (StockTransferLine $line) => [
                    'product_id' => $line->product_id,
                    'product_name' => $line->product->displayName(),
                    'batch_id' => $line->batch_id,
                    'lot_number' => $line->batch->lot_number,
                    'expires_on' => $line->batch->expires_on->toDateString(),
                    'quantity_base' => $line->quantity_base,
                ])->values(),
            ],
            'branches' => $this->branches($request),
        ]);
    }

    public function update(Request $request, StockTransfer $transfer, StockControlService $control): RedirectResponse
    {
        abort_unless($this->user($request)->branch_id === $transfer->branch_id, 404);
        $data = $this->validated($request);
        $transfer = $control->saveTransfer($transfer, $transfer->branch_id, $data['to_branch_id'], $data['note'], $data['lines'], $this->user($request));

        return $this->afterSave($request, $transfer, $control);
    }

    public function receive(Request $request, StockTransfer $transfer, StockControlService $control): RedirectResponse
    {
        $control->receive($transfer, $this->user($request));
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Transfer received. Stock added to this branch.')]);

        return to_route('stock.transfers.show', $transfer);
    }

    private function afterSave(Request $request, StockTransfer $transfer, StockControlService $control): RedirectResponse
    {
        if ($request->boolean('dispatch')) {
            $transfer = $control->dispatch($transfer, $this->user($request));
            Inertia::flash('toast', ['type' => 'success', 'message' => __('Transfer :number dispatched.', ['number' => $transfer->number])]);
        } else {
            Inertia::flash('toast', ['type' => 'success', 'message' => __('Draft saved.')]);
        }

        return to_route('stock.transfers.show', $transfer);
    }

    /**
     * @return array{to_branch_id: int, note: string|null, lines: list<array{batch_id: int, quantity_base: int}>}
     */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'to_branch_id' => ['required', 'integer', Rule::exists('branches', 'id')->where('is_active', true)],
            'note' => ['nullable', 'string', 'max:500'],
            'lines' => ['required', 'array', 'min:1', 'max:200'],
            'lines.*.batch_id' => ['required', 'integer', 'distinct'],
            'lines.*.quantity_base' => ['required', 'integer', 'min:1'],
        ]);

        return [
            'to_branch_id' => (int) $data['to_branch_id'],
            'note' => $data['note'] ?? null,
            'lines' => array_values(array_map(fn (array $line) => ['batch_id' => (int) $line['batch_id'], 'quantity_base' => (int) $line['quantity_base']], $data['lines'])),
        ];
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function branches(Request $request): array
    {
        return array_values(Branch::query()->where('is_active', true)->whereKeyNot($this->user($request)->branch_id)->orderBy('name')->get(['id', 'name'])
            ->map(fn (Branch $branch) => ['value' => (string) $branch->id, 'label' => $branch->name])->all());
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->branch_id !== null, 403);

        return $user;
    }
}
