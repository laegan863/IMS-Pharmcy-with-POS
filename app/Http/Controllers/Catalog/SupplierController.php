<?php

namespace App\Http\Controllers\Catalog;

use App\Http\Controllers\Controller;
use App\Models\Supplier;
use App\Support\TableQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class SupplierController extends Controller
{
    public function index(Request $request): Response
    {
        $table = TableQuery::for(Supplier::query(), $request)
            ->searchUsing(fn (Builder $query, string $term) => $query
                ->where('name', 'like', "%{$term}%")
                ->orWhere('code', 'like', "%{$term}%")
                ->orWhere('tin', 'like', "%{$term}%"))
            ->sortable(['code', 'name', 'fda_license_expires_on'])
            ->defaultSort('name')
            ->filter('status', fn (Builder $query, string $value) => $query->where('is_active', $value === 'active'))
            ->paginate(fn (Supplier $supplier) => [
                ...$supplier->only(['id', 'code', 'name', 'tin', 'phone', 'payment_terms_days', 'is_active']),
                'fda_license_expires_on' => $supplier->fda_license_expires_on?->toDateString(),
                'fda_license_expired' => $supplier->fdaLicenseExpired(),
            ]);

        return Inertia::render('catalog/suppliers/index', $table);
    }

    public function create(): Response
    {
        return Inertia::render('catalog/suppliers/form', ['supplier' => null]);
    }

    public function store(Request $request): RedirectResponse
    {
        Supplier::create($this->validated($request));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Supplier created.')]);

        return to_route('catalog.suppliers.index');
    }

    public function edit(Supplier $supplier): Response
    {
        return Inertia::render('catalog/suppliers/form', [
            'supplier' => [
                ...$supplier->toArray(),
                'fda_license_expires_on' => $supplier->fda_license_expires_on?->toDateString(),
            ],
        ]);
    }

    public function update(Request $request, Supplier $supplier): RedirectResponse
    {
        $supplier->update($this->validated($request, $supplier));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Supplier saved.')]);

        return to_route('catalog.suppliers.index');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?Supplier $supplier = null): array
    {
        return $request->validate([
            'code' => ['required', 'string', 'max:20', 'alpha_dash', Rule::unique('suppliers', 'code')->ignore($supplier?->id)],
            'name' => ['required', 'string', 'max:150'],
            'tin' => ['nullable', 'string', 'max:20', 'regex:/^[0-9-]*$/'],
            'address' => ['nullable', 'string', 'max:255'],
            'fda_license_number' => ['nullable', 'string', 'max:50'],
            'fda_license_expires_on' => ['nullable', 'date'],
            'payment_terms_days' => ['required', 'integer', 'min:0', 'max:365'],
            'contact_person' => ['nullable', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'is_active' => ['boolean'],
        ]);
    }
}
