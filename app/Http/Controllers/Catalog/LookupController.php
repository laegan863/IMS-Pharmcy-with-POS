<?php

namespace App\Http\Controllers\Catalog;

use App\Http\Controllers\Controller;
use App\Models\Lookup;
use App\Support\TableQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * One page for the four product pick-lists: generic names, categories,
 * dosage forms and manufacturers. {type} is a key of Lookup::types().
 */
class LookupController extends Controller
{
    public function index(Request $request, string $type): Response
    {
        $model = $this->model($type);

        $table = TableQuery::for($model::query(), $request)
            ->searchUsing(fn (Builder $query, string $term) => $query->where('name', 'like', "%{$term}%"))
            ->sortable(['name'])
            ->defaultSort('name')
            ->paginate(fn (Lookup $lookup) => $lookup->only(['id', 'name', 'is_active']));

        return Inertia::render('catalog/lookups', [
            ...$table,
            'type' => $type,
            'types' => collect(Lookup::types())->map(fn (array $definition, string $key) => [
                'value' => $key,
                'label' => $definition['label'],
            ])->values(),
        ]);
    }

    public function store(Request $request, string $type): RedirectResponse
    {
        $model = $this->model($type);
        $model::query()->create($this->validated($request, $model));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Added.')]);

        return back();
    }

    public function update(Request $request, string $type, int $id): RedirectResponse
    {
        $model = $this->model($type);
        $lookup = $model::query()->findOrFail($id);
        $lookup->update($this->validated($request, $model, $id));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Saved.')]);

        return back();
    }

    /**
     * @return class-string<Lookup>
     */
    private function model(string $type): string
    {
        $types = Lookup::types();
        abort_unless(array_key_exists($type, $types), 404);

        return $types[$type]['model'];
    }

    /**
     * @param  class-string<Lookup>  $model
     * @return array<string, mixed>
     */
    private function validated(Request $request, string $model, ?int $id = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:150', Rule::unique((new $model)->getTable(), 'name')->ignore($id)],
            'is_active' => ['boolean'],
        ]);
    }
}
