<?php

namespace App\Http\Controllers\Pos;

use App\Http\Controllers\Controller;
use App\Models\Reading;
use App\Models\Terminal;
use App\Models\User;
use App\Services\Sales\ZReadingService;
use App\Settings\Settings;
use App\Support\TableQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Inertia\Inertia;
use Inertia\Response;

class ReadingController extends Controller
{
    public function index(Request $request): Response
    {
        $table = TableQuery::for(Reading::query()->with(['terminal', 'user:id,name']), $request)
            ->sortable(['created_at'])
            ->defaultSort('created_at', 'desc')
            ->paginate(fn (Reading $reading) => [
                'id' => $reading->id,
                'number' => $reading->number,
                'z_counter' => $reading->z_counter,
                'terminal' => $reading->terminal->code,
                'period_start' => $reading->period_start->toIso8601String(),
                'period_end' => $reading->period_end->toIso8601String(),
                'net_sales' => $reading->data['net_sales'] ?? '0.00',
                'grand_total_after' => $reading->grand_total_after,
                'user' => $reading->user->name,
            ]);

        return Inertia::render('pos/readings', [
            ...$table,
            'terminals' => Terminal::query()->where('is_active', true)->orderBy('code')->get()
                ->map(fn (Terminal $terminal) => ['value' => (string) $terminal->id, 'label' => $terminal->code.' · '.$terminal->name]),
        ]);
    }

    public function store(Request $request, ZReadingService $readings): RedirectResponse
    {
        $data = $request->validate(['terminal_id' => ['required', 'integer', Rule::exists('terminals', 'id')]]);
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $reading = $readings->generate(Terminal::query()->findOrFail((int) $data['terminal_id']), $user);

        return to_route('pos.readings.show', $reading);
    }

    public function show(Reading $reading, Settings $settings): View
    {
        $reading->load(['terminal', 'user']);

        return view('pos.z-reading', ['reading' => $reading, 'store' => $settings->all()]);
    }
}
