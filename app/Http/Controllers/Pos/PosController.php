<?php

namespace App\Http\Controllers\Pos;

use App\Http\Controllers\Controller;
use App\Models\HeldSale;
use App\Models\Payment;
use App\Models\Shift;
use App\Models\User;
use App\Services\Sales\SaleService;
use App\Services\Sales\ShiftService;
use App\Settings\Settings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The sale screen and its JSON endpoints. The cart lives in the browser so
 * scanning is instant; the server prices it (quote) and posts it (complete).
 */
class PosController extends Controller
{
    public function screen(Request $request, ShiftService $shifts, Settings $settings): Response|RedirectResponse
    {
        $user = $this->user($request);
        $shift = $shifts->current($user);

        if ($shift === null && $settings->bool('pos.require_shift')) {
            return to_route('pos.shifts.create');
        }

        abort_if($shift === null, 403, __('Open a shift before selling.'));

        return Inertia::render('pos/terminal', [
            'shift' => [
                'id' => $shift->id,
                'number' => $shift->number,
                'terminal' => $shift->terminal->code.' · '.$shift->terminal->name,
                'opened_at' => $shift->opened_at->toIso8601String(),
            ],
            'paymentMethods' => Payment::METHODS,
            'idleTimeoutMinutes' => $settings->int('pos.idle_timeout_minutes'),
            'heldCount' => HeldSale::query()->where('terminal_id', $shift->terminal_id)->count(),
        ]);
    }

    public function quote(Request $request, SaleService $sales): JsonResponse
    {
        $data = $request->validate([
            'lines' => ['present', 'array', 'max:200'],
            'lines.*.product_id' => ['required', 'integer'],
            'lines.*.product_unit_id' => ['required', 'integer'],
            'lines.*.quantity' => ['required', 'integer', 'min:1', 'max:100000'],
            'lines.*.unit_price' => ['nullable', 'numeric', 'min:0'],
            'discount_type' => ['required', 'in:none,senior,pwd'],
        ]);

        if ($data['lines'] === []) {
            return response()->json(['lines' => [], 'totals' => null]);
        }

        return response()->json($sales->quote((int) $this->user($request)->branch_id, $data['lines'], $data['discount_type']));
    }

    public function complete(Request $request, SaleService $sales, ShiftService $shifts): JsonResponse
    {
        $data = $request->validate([
            'client_uuid' => ['required', 'uuid'],
            'lines' => ['required', 'array', 'min:1', 'max:200'],
            'lines.*.product_id' => ['required', 'integer'],
            'lines.*.product_unit_id' => ['required', 'integer'],
            'lines.*.quantity' => ['required', 'integer', 'min:1', 'max:100000'],
            'lines.*.unit_price' => ['nullable', 'numeric', 'min:0'],
            'lines.*.batch_id' => ['nullable', 'integer'],
            'discount_type' => ['required', 'in:none,senior,pwd'],
            'discount_id_number' => ['nullable', 'string', 'max:50'],
            'discount_holder_name' => ['nullable', 'string', 'max:150'],
            'customer_id' => ['nullable', 'integer'],
            'payments' => ['required', 'array', 'min:1', 'max:5'],
            'payments.*.method' => ['required', 'in:cash,ewallet,card'],
            'payments.*.amount' => ['required', 'numeric', 'min:0.01'],
            'payments.*.reference' => ['nullable', 'string', 'max:100'],
            'approver' => ['nullable', 'string'],
            'approver_pin' => ['nullable', 'string'],
        ]);

        $user = $this->user($request);
        $shift = $shifts->current($user);
        abort_if($shift === null, 422, __('Open a shift before selling.'));

        $sale = $sales->complete($data, $user, $shift);

        return response()->json([
            'id' => $sale->id,
            'number' => $sale->number,
            'total' => $sale->total,
            'change_due' => $sale->change_due,
            'receipt_url' => route('pos.sales.receipt', $sale),
        ]);
    }

    public function heldIndex(Request $request, ShiftService $shifts): JsonResponse
    {
        $shift = $this->shift($request, $shifts);

        return response()->json([
            'data' => HeldSale::query()
                ->where('terminal_id', $shift->terminal_id)
                ->latest()
                ->get(['id', 'label', 'created_at'])
                ->map(fn (HeldSale $held) => ['id' => $held->id, 'label' => $held->label, 'created_at' => $held->created_at?->toIso8601String()]),
        ]);
    }

    public function hold(Request $request, ShiftService $shifts): JsonResponse
    {
        $data = $request->validate([
            'label' => ['required', 'string', 'max:100'],
            'cart' => ['required', 'array'],
        ]);
        $shift = $this->shift($request, $shifts);

        $held = HeldSale::query()->create([
            'branch_id' => $shift->branch_id,
            'terminal_id' => $shift->terminal_id,
            'user_id' => $this->user($request)->id,
            'label' => $data['label'],
            'cart' => $data['cart'],
        ]);

        return response()->json(['id' => $held->id], 201);
    }

    /**
     * Returns the held cart and removes it from the list.
     */
    public function resume(Request $request, HeldSale $held, ShiftService $shifts): JsonResponse
    {
        abort_unless($held->terminal_id === $this->shift($request, $shifts)->terminal_id, 404);

        $cart = $held->cart;
        $held->delete();

        return response()->json(['cart' => $cart]);
    }

    private function shift(Request $request, ShiftService $shifts): Shift
    {
        $shift = $shifts->current($this->user($request));
        abort_if($shift === null, 422, __('Open a shift first.'));

        return $shift;
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->branch_id !== null, 403);

        return $user;
    }
}
