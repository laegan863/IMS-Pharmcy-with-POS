<?php

namespace App\Http\Controllers\Stock;

use App\Enums\AdjustmentReason;
use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\StockAdjustment;
use App\Models\StockAdjustmentLine;
use App\Models\User;
use App\Services\Stock\StockControlService;
use App\Support\TableQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Stock adjustments and disposals ({type} is adjustment or disposal).
 */
class StockAdjustmentController extends Controller
{
    public function index(Request $request, string $type): Response
    {
        $this->assertType($type);

        $table = TableQuery::for(StockAdjustment::query()->with('creator:id,name')->where('type', $type), $request)
            ->searchUsing(fn (Builder $query, string $term) => $query->where('number', 'like', "%{$term}%")->orWhere('note', 'like', "%{$term}%"))
            ->sortable(['created_at', 'number'])
            ->defaultSort('created_at', 'desc')
            ->filter('status', fn (Builder $query, string $value) => $query->where('status', $value))
            ->paginate(fn (StockAdjustment $adjustment) => [
                'id' => $adjustment->id,
                'number' => $adjustment->number,
                'status' => $adjustment->status,
                'reason' => $adjustment->reason_code->label(),
                'value' => $adjustment->value,
                'created_by' => $adjustment->creator->name,
                'created_at' => $adjustment->created_at?->toIso8601String(),
            ]);

        return Inertia::render('stock/adjustments/index', [...$table, 'type' => $type]);
    }

    public function create(string $type): Response
    {
        $this->assertType($type);

        return Inertia::render('stock/adjustments/form', ['type' => $type, 'adjustment' => null, 'reasons' => AdjustmentReason::options(), 'canPost' => false]);
    }

    public function store(Request $request, string $type, StockControlService $control): RedirectResponse
    {
        $this->assertType($type);
        [$header, $lines] = $this->validated($request);
        $adjustment = $control->saveAdjustment(null, (int) $this->user($request)->branch_id, $type, $header, $lines, $this->user($request));

        return $this->afterSave($request, $adjustment, $control);
    }

    public function show(Request $request, StockAdjustment $adjustment): Response
    {
        $adjustment->load(['lines.batch', 'lines.product.genericName', 'lines.product.dosageForm', 'creator:id,name', 'approver:id,name']);

        return Inertia::render('stock/adjustments/form', [
            'type' => $adjustment->type,
            'adjustment' => [
                'id' => $adjustment->id,
                'number' => $adjustment->number,
                'status' => $adjustment->status,
                'reason_code' => $adjustment->reason_code->value,
                'note' => $adjustment->note,
                'disposal_method' => $adjustment->disposal_method,
                'witness_name' => $adjustment->witness_name,
                'value' => $adjustment->value,
                'created_by' => $adjustment->creator->name,
                'approved_by' => $adjustment->approver?->name,
                'posted_at' => $adjustment->posted_at?->toIso8601String(),
                'lines' => $adjustment->lines->map(fn (StockAdjustmentLine $line) => [
                    'product_id' => $line->product_id,
                    'product_name' => $line->product->displayName(),
                    'batch_id' => $line->batch_id,
                    'lot_number' => $line->batch->lot_number,
                    'expires_on' => $line->batch->expires_on->toDateString(),
                    'quantity_change' => $adjustment->type === 'disposal' ? -$line->quantity_change : $line->quantity_change,
                    'unit_cost' => $line->batch->unit_cost,
                ])->values(),
            ],
            'reasons' => AdjustmentReason::options(),
            'canPost' => $adjustment->status === 'draft' && $this->user($request)->can(Permission::StockAdjustApprove->value),
        ]);
    }

    public function update(Request $request, StockAdjustment $adjustment, StockControlService $control): RedirectResponse
    {
        [$header, $lines] = $this->validated($request);
        $adjustment = $control->saveAdjustment($adjustment, $adjustment->branch_id, $adjustment->type, $header, $lines, $this->user($request));

        return $this->afterSave($request, $adjustment, $control);
    }

    private function afterSave(Request $request, StockAdjustment $adjustment, StockControlService $control): RedirectResponse
    {
        if ($request->boolean('post')) {
            try {
                $adjustment = $control->postAdjustment($adjustment, $this->user($request));
            } catch (ValidationException $exception) {
                return to_route('stock.adjustments.show', $adjustment)->withErrors($exception->errors());
            }

            Inertia::flash('toast', ['type' => 'success', 'message' => __(':number posted. Stock updated.', ['number' => $adjustment->number])]);
        } else {
            Inertia::flash('toast', ['type' => 'success', 'message' => __('Draft saved. A pharmacist or the owner posts it.')]);
        }

        return to_route('stock.adjustments.show', $adjustment);
    }

    /**
     * @return array{0: array{reason_code: string, note: string|null, disposal_method: string|null, witness_name: string|null}, 1: list<array{batch_id: int, quantity_change: int}>}
     */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'reason_code' => ['required', new Enum(AdjustmentReason::class)],
            'note' => ['nullable', 'string', 'max:500'],
            'disposal_method' => ['nullable', 'string', 'max:100'],
            'witness_name' => ['nullable', 'string', 'max:150'],
            'lines' => ['required', 'array', 'min:1', 'max:200'],
            'lines.*.batch_id' => ['required', 'integer', 'distinct'],
            'lines.*.quantity_change' => ['required', 'integer', 'not_in:0', 'min:-1000000', 'max:1000000'],
        ]);

        return [
            [
                'reason_code' => (string) $data['reason_code'],
                'note' => $data['note'] ?? null,
                'disposal_method' => $data['disposal_method'] ?? null,
                'witness_name' => $data['witness_name'] ?? null,
            ],
            array_values(array_map(fn (array $line) => ['batch_id' => (int) $line['batch_id'], 'quantity_change' => (int) $line['quantity_change']], $data['lines'])),
        ];
    }

    private function assertType(string $type): void
    {
        abort_unless(in_array($type, ['adjustment', 'disposal'], true), 404);
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->branch_id !== null, 403);

        return $user;
    }
}
