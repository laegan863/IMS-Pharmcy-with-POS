<?php

namespace App\Http\Controllers\Pos;

use App\Http\Controllers\Controller;
use App\Models\Shift;
use App\Models\Terminal;
use App\Models\User;
use App\Services\Sales\ShiftService;
use App\Settings\Settings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Inertia\Inertia;
use Inertia\Response;

class ShiftController extends Controller
{
    public function create(Request $request, ShiftService $shifts): Response|RedirectResponse
    {
        if ($shifts->current($this->user($request)) !== null) {
            return to_route('pos.screen');
        }

        $busy = Shift::query()->where('status', 'open')->pluck('terminal_id')->all();

        return Inertia::render('pos/shift-open', [
            'terminals' => Terminal::query()
                ->where('is_active', true)
                ->orderBy('code')
                ->get()
                ->map(fn (Terminal $terminal) => [
                    'value' => (string) $terminal->id,
                    'label' => $terminal->code.' · '.$terminal->name.(in_array($terminal->id, $busy, true) ? ' (in use)' : ''),
                ]),
        ]);
    }

    public function store(Request $request, ShiftService $shifts): RedirectResponse
    {
        $data = $request->validate([
            'terminal_id' => ['required', 'integer', Rule::exists('terminals', 'id')],
            'opening_cash' => ['required', 'numeric', 'min:0', 'max:1000000'],
        ]);

        $terminal = Terminal::query()->findOrFail((int) $data['terminal_id']);
        $shifts->open($this->user($request), $terminal, (string) $data['opening_cash']);

        return to_route('pos.screen');
    }

    public function cash(Request $request, ShiftService $shifts): RedirectResponse
    {
        $data = $request->validate([
            'type' => ['required', 'in:in,out'],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:1000000'],
            'reason' => ['required', 'string', 'max:255'],
        ]);

        $user = $this->user($request);
        $shift = $shifts->current($user);
        abort_if($shift === null, 422);

        $shifts->cashMovement($shift, $user, $data['type'], (string) $data['amount'], $data['reason']);

        return back();
    }

    public function count(Request $request, ShiftService $shifts): Response|RedirectResponse
    {
        $shift = $shifts->current($this->user($request));

        if ($shift === null) {
            return to_route('pos.shifts.create');
        }

        return Inertia::render('pos/shift-close', [
            'shift' => ['id' => $shift->id, 'number' => $shift->number, 'opening_cash' => $shift->opening_cash],
            'denominations' => ShiftService::DENOMINATIONS,
        ]);
    }

    public function close(Request $request, ShiftService $shifts): RedirectResponse
    {
        $data = $request->validate([
            'denominations' => ['required', 'array'],
            'denominations.*' => ['nullable', 'integer', 'min:0', 'max:100000'],
        ]);

        $shift = $shifts->current($this->user($request));
        abort_if($shift === null, 422);

        /** @var array<string, int> $denominations */
        $denominations = array_map('intval', array_filter($data['denominations'], fn ($value) => $value !== null));
        $shift = $shifts->close($shift, $denominations);

        return to_route('pos.shifts.x-reading', $shift);
    }

    /**
     * The printable X-reading of a closed shift.
     */
    public function xReading(Shift $shift, Settings $settings): View
    {
        $shift->load(['terminal', 'user']);

        return view('pos.x-reading', [
            'shift' => $shift,
            'reading' => $shift->x_reading ?? [],
            'store' => $settings->all(),
        ]);
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->branch_id !== null, 403);

        return $user;
    }
}
