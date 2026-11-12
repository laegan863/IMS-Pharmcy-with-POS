<?php

namespace App\Http\Controllers\Pos;

use App\Http\Controllers\Controller;
use App\Models\Sale;
use App\Models\SaleAdjustment;
use App\Models\SaleLine;
use App\Models\User;
use App\Services\Sales\SaleAdjustmentService;
use App\Services\Sales\ShiftService;
use App\Settings\Settings;
use App\Support\TableQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Invoices: list, detail, the printed receipt (reprints are marked), and the
 * void and refund actions.
 */
class SaleController extends Controller
{
    public function index(Request $request): Response
    {
        $table = TableQuery::for(Sale::query()->with(['cashier:id,name', 'terminal']), $request)
            ->searchUsing(fn (Builder $query, string $term) => $query
                ->where('number', 'like', "%{$term}%")
                ->orWhere('discount_holder_name', 'like', "%{$term}%"))
            ->sortable(['number', 'completed_at', 'total'])
            ->defaultSort('completed_at', 'desc')
            ->filter('status', fn (Builder $query, string $value) => $query->where('status', $value))
            ->filter('discount', fn (Builder $query, string $value) => $query->where('discount_type', $value))
            ->filter('date', fn (Builder $query, string $value) => $query->whereDate('completed_at', $value))
            ->paginate(fn (Sale $sale) => [
                'id' => $sale->id,
                'number' => $sale->number,
                'completed_at' => $sale->completed_at->toIso8601String(),
                'cashier' => $sale->cashier->name,
                'terminal' => $sale->terminal->code,
                'discount_type' => $sale->discount_type,
                'status' => $sale->status,
                'total' => $sale->total,
            ]);

        return Inertia::render('pos/sales/index', $table);
    }

    public function show(Request $request, Sale $sale, ShiftService $shifts): Response
    {
        $sale->load(['lines', 'payments', 'cashier:id,name', 'terminal', 'customer:id,name', 'adjustments.approver:id,name']);
        $user = $request->user();

        return Inertia::render('pos/sales/show', [
            'sale' => [
                'id' => $sale->id,
                'number' => $sale->number,
                'status' => $sale->status,
                'completed_at' => $sale->completed_at->toIso8601String(),
                'cashier' => $sale->cashier->name,
                'terminal' => $sale->terminal->code,
                'customer' => $sale->customer?->name,
                'discount_type' => $sale->discount_type,
                'discount_holder_name' => $sale->discount_holder_name,
                'totals' => $sale->only(['gross_amount', 'vatable_sales', 'vat_amount', 'vat_exempt_sales', 'zero_rated_sales', 'statutory_discount', 'total', 'amount_paid', 'change_due']),
                'lines' => $sale->lines->map(fn (SaleLine $line) => [
                    'id' => $line->id,
                    'description' => $line->description,
                    'quantity' => $line->quantity,
                    'quantity_base' => $line->quantity_base,
                    'refundable_base' => $line->refundableBase(),
                    'unit_price' => $line->unit_price,
                    'discount_amount' => $line->discount_amount,
                    'line_total' => $line->line_total,
                ])->values(),
                'payments' => $sale->payments->map->only(['method', 'amount', 'reference'])->values(),
                'adjustments' => $sale->adjustments->map(fn (SaleAdjustment $adjustment) => [
                    'number' => $adjustment->number,
                    'type' => $adjustment->type,
                    'amount' => $adjustment->amount,
                    'reason' => $adjustment->reason,
                    'approved_by' => $adjustment->approver->name,
                    'created_at' => $adjustment->created_at->toIso8601String(),
                ])->values(),
            ],
            'canAdjust' => $user instanceof User && $shifts->current($user) !== null,
        ]);
    }

    /**
     * The 80 mm receipt. Opening it again counts and prints as a reprint.
     */
    public function receipt(Request $request, Sale $sale, Settings $settings): View
    {
        $sale->load(['lines', 'payments', 'cashier', 'terminal', 'branch']);
        $isReprint = $sale->printed_at !== null;

        if ($isReprint) {
            $sale->increment('reprint_count');
            activity('pos')->performedOn($sale)->event('reprint')->log('Receipt reprinted');
        } else {
            $sale->update(['printed_at' => now()]);
        }

        return view('pos.receipt', [
            'sale' => $sale,
            'store' => $settings->all(),
            'isReprint' => $isReprint,
        ]);
    }

    public function void(Request $request, Sale $sale, SaleAdjustmentService $adjustments, ShiftService $shifts): RedirectResponse
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'max:255'],
            'approver' => ['required', 'string'],
            'approver_pin' => ['required', 'string'],
        ]);

        $user = $this->user($request);
        $shift = $shifts->current($user);
        abort_if($shift === null, 422, __('Open a shift first.'));

        $void = $adjustments->void($sale, $shift, $user, $data['reason'], $data['approver'], $data['approver_pin']);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Sale voided as :number.', ['number' => $void->number])]);

        return to_route('pos.sales.show', $sale);
    }

    public function refund(Request $request, Sale $sale, SaleAdjustmentService $adjustments, ShiftService $shifts): RedirectResponse
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'max:255'],
            'approver' => ['required', 'string'],
            'approver_pin' => ['required', 'string'],
            'quantities' => ['required', 'array'],
            'quantities.*' => ['integer', 'min:0'],
        ]);

        $user = $this->user($request);
        $shift = $shifts->current($user);
        abort_if($shift === null, 422, __('Open a shift first.'));

        /** @var array<int, int> $quantities */
        $quantities = array_map('intval', $data['quantities']);
        $refund = $adjustments->refund($sale, $shift, $user, $quantities, $data['reason'], $data['approver'], $data['approver_pin']);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Refund :number for :amount.', ['number' => $refund->number, 'amount' => $refund->amount])]);

        return to_route('pos.sales.show', $sale);
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
