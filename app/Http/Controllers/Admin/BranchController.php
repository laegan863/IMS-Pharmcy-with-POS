<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Scopes\BranchScope;
use App\Models\Terminal;
use App\Support\TableQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Branches and their POS terminals. Owners manage every branch, so terminal
 * queries here deliberately remove the branch scope.
 */
class BranchController extends Controller
{
    public function index(Request $request): Response
    {
        $table = TableQuery::for(Branch::query()->withCount(['users', 'terminals' => fn (Builder $query) => $query->withoutGlobalScope(BranchScope::class)]), $request)
            ->searchUsing(fn (Builder $query, string $term) => $query
                ->where('name', 'like', "%{$term}%")
                ->orWhere('code', 'like', "%{$term}%"))
            ->sortable(['code', 'name'])
            ->defaultSort('code')
            ->paginate(fn (Branch $branch) => [
                'id' => $branch->id,
                'code' => $branch->code,
                'name' => $branch->name,
                'address' => $branch->address,
                'is_active' => $branch->is_active,
                'users_count' => $branch->getAttribute('users_count'),
                'terminals_count' => $branch->getAttribute('terminals_count'),
            ]);

        return Inertia::render('admin/branches/index', $table);
    }

    public function create(): Response
    {
        return Inertia::render('admin/branches/form', ['branch' => null, 'terminals' => []]);
    }

    public function store(Request $request): RedirectResponse
    {
        $branch = Branch::create($this->validated($request));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Branch created.')]);

        return to_route('admin.branches.edit', $branch);
    }

    public function edit(Branch $branch): Response
    {
        return Inertia::render('admin/branches/form', [
            'branch' => $branch->only(['id', 'code', 'name', 'address', 'phone', 'is_active']),
            'terminals' => Terminal::withoutGlobalScope(BranchScope::class)
                ->whereBelongsTo($branch)
                ->orderBy('code')
                ->get()
                ->map(fn (Terminal $terminal) => $terminal->only([
                    'id', 'code', 'name', 'machine_identification_number', 'permit_to_use_number', 'serial_number', 'is_active',
                ])),
        ]);
    }

    public function update(Request $request, Branch $branch): RedirectResponse
    {
        $branch->update($this->validated($request, $branch));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Branch updated.')]);

        return to_route('admin.branches.edit', $branch);
    }

    public function storeTerminal(Request $request, Branch $branch): RedirectResponse
    {
        Terminal::create([...$this->validatedTerminal($request, $branch), 'branch_id' => $branch->id]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Terminal added.')]);

        return to_route('admin.branches.edit', $branch);
    }

    public function updateTerminal(Request $request, Branch $branch, int $terminal): RedirectResponse
    {
        $model = Terminal::withoutGlobalScope(BranchScope::class)->whereBelongsTo($branch)->findOrFail($terminal);
        $model->update($this->validatedTerminal($request, $branch, $model));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Terminal updated.')]);

        return to_route('admin.branches.edit', $branch);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?Branch $branch = null): array
    {
        return $request->validate([
            'code' => ['required', 'string', 'max:20', 'alpha_dash', Rule::unique('branches', 'code')->ignore($branch?->id)],
            'name' => ['required', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'is_active' => ['boolean'],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedTerminal(Request $request, Branch $branch, ?Terminal $terminal = null): array
    {
        return $request->validate([
            'code' => ['required', 'string', 'max:20', 'alpha_dash', Rule::unique('terminals', 'code')->where('branch_id', $branch->id)->ignore($terminal?->id)],
            'name' => ['required', 'string', 'max:255'],
            'machine_identification_number' => ['nullable', 'string', 'max:50', Rule::unique('terminals', 'machine_identification_number')->ignore($terminal?->id)],
            'permit_to_use_number' => ['nullable', 'string', 'max:50'],
            'serial_number' => ['nullable', 'string', 'max:100'],
            'is_active' => ['boolean'],
        ]);
    }
}
