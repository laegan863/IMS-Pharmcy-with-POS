<?php

namespace App\Http\Controllers\Catalog;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Support\TableQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Customers, mostly for senior citizen and PWD discounts and prescriptions.
 * ID numbers are shown masked; searching by ID number uses the keyed hash.
 */
class CustomerController extends Controller
{
    public function index(Request $request): Response
    {
        $table = TableQuery::for(Customer::query(), $request)
            ->searchUsing(fn (Builder $query, string $term) => $this->search($query, $term))
            ->sortable(['name', 'created_at'])
            ->defaultSort('name')
            ->filter('type', fn (Builder $query, string $value) => match ($value) {
                'senior' => $query->whereNotNull('senior_citizen_id_hash'),
                'pwd' => $query->whereNotNull('pwd_id_hash'),
                default => $query,
            })
            ->paginate(fn (Customer $customer) => $this->row($customer));

        return Inertia::render('catalog/customers/index', $table);
    }

    /**
     * JSON lookup for the POS customer picker.
     */
    public function lookup(Request $request): JsonResponse
    {
        $term = trim((string) $request->query('q', ''));

        $customers = $term === ''
            ? collect()
            : $this->search(Customer::query()->where('is_active', true), $term)->orderBy('name')->limit(10)->get();

        return response()->json(['data' => $customers->map(fn (Customer $customer) => $this->row($customer))->values()]);
    }

    public function create(): Response
    {
        return Inertia::render('catalog/customers/form', ['customer' => null]);
    }

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $customer = Customer::create($this->validated($request));

        if ($request->wantsJson() && ! $request->header('X-Inertia')) {
            return response()->json(['data' => $this->row($customer)], 201);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Customer created.')]);

        return to_route('catalog.customers.index');
    }

    public function edit(Customer $customer): Response
    {
        return Inertia::render('catalog/customers/form', ['customer' => $this->row($customer)]);
    }

    public function update(Request $request, Customer $customer): RedirectResponse
    {
        $data = $this->validated($request);

        // Blank ID fields on edit keep the stored (encrypted) number.
        foreach (['senior_citizen_id', 'pwd_id'] as $field) {
            if (blank($data[$field] ?? null) && ! $request->boolean("clear_{$field}")) {
                unset($data[$field]);
            }
        }

        $customer->update($data);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Customer saved.')]);

        return to_route('catalog.customers.index');
    }

    /**
     * @param  Builder<Customer>  $query
     * @return Builder<Customer>
     */
    private function search(Builder $query, string $term): Builder
    {
        $hash = Customer::hashId($term);

        return $query->where(fn (Builder $query) => $query
            ->where('name', 'like', "%{$term}%")
            ->orWhere('phone', 'like', "%{$term}%")
            ->when($hash, fn (Builder $query) => $query
                ->orWhere('senior_citizen_id_hash', $hash)
                ->orWhere('pwd_id_hash', $hash)));
    }

    /**
     * @return array<string, mixed>
     */
    private function row(Customer $customer): array
    {
        return [
            'id' => $customer->id,
            'name' => $customer->name,
            'phone' => $customer->phone,
            'address' => $customer->address,
            'birthdate' => $customer->birthdate?->toDateString(),
            'senior_citizen_id_masked' => Customer::maskId($customer->senior_citizen_id),
            'pwd_id_masked' => Customer::maskId($customer->pwd_id),
            'is_active' => $customer->is_active,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:255'],
            'birthdate' => ['nullable', 'date', 'before:today'],
            'senior_citizen_id' => ['nullable', 'string', 'max:50'],
            'pwd_id' => ['nullable', 'string', 'max:50'],
            'is_active' => ['boolean'],
        ]);
    }
}
