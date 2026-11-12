<?php

namespace App\Http\Controllers\Stock;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\StockCount;
use App\Models\StockCountLine;
use App\Models\User;
use App\Services\Stock\StockControlService;
use App\Support\TableQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Physical counts: start (freezes system quantities), enter counts,
 * review the variances, post.
 */
class StockCountController extends Controller
{
    public function index(Request $request): Response
    {
        $table = TableQuery::for(StockCount::query()->with('creator:id,name')->withCount('lines'), $request)
            ->sortable(['created_at'])
            ->defaultSort('created_at', 'desc')
            ->paginate(fn (StockCount $count) => [
                'id' => $count->id,
                'number' => $count->number,
                'status' => $count->status,
                'scope' => $count->scope,
                'lines' => $count->getAttribute('lines_count'),
                'created_by' => $count->creator->name,
                'created_at' => $count->created_at?->toIso8601String(),
            ]);

        return Inertia::render('stock/counts/index', [...$table, 'categories' => Category::options()]);
    }

    public function store(Request $request, StockControlService $control): RedirectResponse
    {
        $data = $request->validate(['category_id' => ['nullable', 'integer', Rule::exists('categories', 'id')]]);
        $user = $this->user($request);

        $count = $control->startCount((int) $user->branch_id, [], isset($data['category_id']) ? (int) $data['category_id'] : null, $user);

        return to_route('stock.counts.show', $count);
    }

    public function show(Request $request, StockCount $count): Response
    {
        $count->load(['lines.batch', 'lines.product.genericName', 'lines.product.dosageForm', 'lines.product.baseUnit']);
        $user = $this->user($request);

        return Inertia::render('stock/counts/show', [
            'count' => [
                'id' => $count->id,
                'number' => $count->number,
                'status' => $count->status,
                'scope' => $count->scope,
                'posted_at' => $count->posted_at?->toIso8601String(),
                'lines' => $count->lines
                    ->sortBy(fn (StockCountLine $line) => $line->product->displayName())
                    ->map(fn (StockCountLine $line) => [
                        'id' => $line->id,
                        'product' => $line->product->displayName(),
                        'base_unit' => $line->product->baseUnit?->name,
                        'lot_number' => $line->batch->lot_number,
                        'expires_on' => $line->batch->expires_on->toDateString(),
                        'system_quantity' => $line->system_quantity,
                        'counted_quantity' => $line->counted_quantity,
                        'variance' => $line->variance(),
                        'unit_cost' => $line->batch->unit_cost,
                    ])->values(),
            ],
            'canPost' => $count->status === 'reviewed' && $user->can(Permission::StockCountApprove->value),
        ]);
    }

    public function update(Request $request, StockCount $count, StockControlService $control): RedirectResponse
    {
        $data = $request->validate([
            'counts' => ['required', 'array'],
            'counts.*' => ['nullable', 'integer', 'min:0', 'max:10000000'],
            'action' => ['nullable', 'in:save,review,post'],
        ]);

        /** @var array<int, int|null> $counted */
        $counted = array_map(fn ($value) => $value === null ? null : (int) $value, $data['counts']);
        $control->recordCounts($count, $counted);
        $user = $this->user($request);

        match ($data['action'] ?? 'save') {
            'review' => $control->review($count->refresh(), $user),
            'post' => $control->postCount($count->refresh(), $user),
            default => null,
        };

        Inertia::flash('toast', ['type' => 'success', 'message' => match ($data['action'] ?? 'save') {
            'review' => __('Count reviewed. A pharmacist or the owner can post it.'),
            'post' => __('Count posted. Variances applied to stock.'),
            default => __('Counts saved.'),
        }]);

        return to_route('stock.counts.show', $count);
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->branch_id !== null, 403);

        return $user;
    }
}
