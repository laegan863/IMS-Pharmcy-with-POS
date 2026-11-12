<?php

namespace App\Http\Controllers\Catalog;

use App\Http\Controllers\Controller;
use App\Models\Prescriber;
use App\Support\TableQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class PrescriberController extends Controller
{
    public function index(Request $request): Response
    {
        $table = TableQuery::for(Prescriber::query(), $request)
            ->searchUsing(fn (Builder $query, string $term) => $query
                ->where('name', 'like', "%{$term}%")
                ->orWhere('prc_license_number', 'like', "%{$term}%"))
            ->sortable(['name'])
            ->defaultSort('name')
            ->paginate(fn (Prescriber $prescriber) => $prescriber->only([
                'id', 'name', 'prc_license_number', 'ptr_number', 's2_license_number', 'specialty', 'is_active',
            ]));

        return Inertia::render('catalog/prescribers/index', $table);
    }

    /**
     * JSON lookup for the prescription form.
     */
    public function lookup(Request $request): JsonResponse
    {
        $term = trim((string) $request->query('q', ''));

        return response()->json([
            'data' => $term === '' ? [] : Prescriber::query()
                ->where('is_active', true)
                ->where(fn (Builder $query) => $query->where('name', 'like', "%{$term}%")->orWhere('prc_license_number', $term))
                ->orderBy('name')
                ->limit(10)
                ->get(['id', 'name', 'prc_license_number', 's2_license_number']),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('catalog/prescribers/form', ['prescriber' => null]);
    }

    public function store(Request $request): RedirectResponse
    {
        Prescriber::create($this->validated($request));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Prescriber created.')]);

        return to_route('catalog.prescribers.index');
    }

    public function edit(Prescriber $prescriber): Response
    {
        return Inertia::render('catalog/prescribers/form', ['prescriber' => $prescriber]);
    }

    public function update(Request $request, Prescriber $prescriber): RedirectResponse
    {
        $prescriber->update($this->validated($request, $prescriber));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Prescriber saved.')]);

        return to_route('catalog.prescribers.index');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?Prescriber $prescriber = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'prc_license_number' => ['nullable', 'string', 'max:30', Rule::unique('prescribers', 'prc_license_number')->ignore($prescriber?->id)],
            'ptr_number' => ['nullable', 'string', 'max:30'],
            's2_license_number' => ['nullable', 'string', 'max:30'],
            'specialty' => ['nullable', 'string', 'max:100'],
            'clinic_address' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'is_active' => ['boolean'],
        ]);
    }
}
