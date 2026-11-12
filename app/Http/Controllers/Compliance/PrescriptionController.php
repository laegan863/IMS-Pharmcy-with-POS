<?php

namespace App\Http\Controllers\Compliance;

use App\Http\Controllers\Controller;
use App\Models\Prescription;
use App\Models\PrescriptionLine;
use App\Models\Product;
use App\Models\User;
use App\Services\Compliance\PrescriptionService;
use App\Services\Stock\StockService;
use App\Support\TableQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PrescriptionController extends Controller
{
    public function index(Request $request): Response
    {
        $table = TableQuery::for(Prescription::query()->with(['prescriber:id,name', 'lines']), $request)
            ->searchUsing(fn (Builder $query, string $term) => $query
                ->where('number', 'like', "%{$term}%")
                ->orWhere('patient_name', 'like', "%{$term}%"))
            ->sortable(['number', 'prescribed_on', 'created_at'])
            ->defaultSort('created_at', 'desc')
            ->filter('open', fn (Builder $query, string $value) => $value === 'open'
                ? $query->whereHas('lines', fn (Builder $lines) => $lines->whereColumn('quantity_dispensed_base', '<', 'quantity_prescribed_base'))
                : $query->whereDoesntHave('lines', fn (Builder $lines) => $lines->whereColumn('quantity_dispensed_base', '<', 'quantity_prescribed_base')))
            ->paginate(fn (Prescription $prescription) => [
                'id' => $prescription->id,
                'number' => $prescription->number,
                'patient_name' => $prescription->patient_name,
                'prescriber' => $prescription->prescriber->name,
                'prescribed_on' => $prescription->prescribed_on->toDateString(),
                'lines' => $prescription->lines->count(),
                'open' => $prescription->lines->contains(fn (PrescriptionLine $line) => $line->balance() > 0),
            ]);

        return Inertia::render('compliance/prescriptions/index', $table);
    }

    public function create(): Response
    {
        return Inertia::render('compliance/prescriptions/form', ['prescription' => null]);
    }

    public function store(Request $request, PrescriptionService $prescriptions): RedirectResponse
    {
        $data = $request->validate([
            ...$this->headerRules(),
            'lines' => ['required', 'array', 'min:1', 'max:30'],
            'lines.*.product_id' => ['required', 'integer', Rule::exists('products', 'id')],
            'lines.*.quantity_prescribed_base' => ['required', 'integer', 'min:1'],
            'lines.*.directions' => ['nullable', 'string', 'max:255'],
            'photo' => ['nullable', 'image', 'max:5120'],
        ]);

        $prescription = $prescriptions->create(
            $this->branchId($request),
            $this->header($data),
            array_values(array_map(fn (array $line) => [
                'product_id' => (int) $line['product_id'],
                'quantity_prescribed_base' => (int) $line['quantity_prescribed_base'],
                'directions' => $line['directions'] ?? null,
            ], $data['lines'])),
            $this->user($request),
            $request->file('photo'),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Prescription :number recorded.', ['number' => $prescription->number])]);

        return to_route('compliance.prescriptions.show', $prescription);
    }

    public function show(Prescription $prescription): Response
    {
        $prescription->load(['prescriber', 'creator:id,name', 'lines.product.genericName', 'lines.product.dosageForm']);

        return Inertia::render('compliance/prescriptions/show', [
            'prescription' => [
                'id' => $prescription->id,
                'number' => $prescription->number,
                'patient_name' => $prescription->patient_name,
                'patient_address' => $prescription->patient_address,
                'prescriber' => $prescription->prescriber->name,
                'prc' => $prescription->prescriber->prc_license_number,
                's2' => $prescription->prescriber->s2_license_number,
                'prescribed_on' => $prescription->prescribed_on->toDateString(),
                'special_form_serial' => $prescription->special_form_serial,
                'notes' => $prescription->notes,
                'has_photo' => $prescription->photo_path !== null,
                'created_by' => $prescription->creator->name,
                'lines' => $prescription->lines->map(fn (PrescriptionLine $line) => [
                    'product' => $line->product->displayName(),
                    'prescribed' => $line->quantity_prescribed_base,
                    'dispensed' => $line->quantity_dispensed_base,
                    'balance' => $line->balance(),
                    'directions' => $line->directions,
                ])->values(),
            ],
        ]);
    }

    public function photo(Prescription $prescription): StreamedResponse
    {
        abort_if($prescription->photo_path === null, 404);
        activity('privacy')->performedOn($prescription)->event('photo-viewed')->log('Prescription photo opened');

        return Storage::disk('local')->response($prescription->photo_path);
    }

    /**
     * Open prescription lines for a product, for the POS prescription picker.
     */
    public function openLines(Request $request, PrescriptionService $prescriptions): JsonResponse
    {
        $product = Product::query()->findOrFail($request->integer('product_id'));

        return response()->json(['data' => $prescriptions->openLinesFor($this->branchId($request), $product)]);
    }

    /**
     * Records a one-line prescription from the POS and returns its line id.
     */
    public function quickStore(Request $request, PrescriptionService $prescriptions): JsonResponse
    {
        $data = $request->validate([
            ...$this->headerRules(),
            'product_id' => ['required', 'integer', Rule::exists('products', 'id')],
            'quantity_prescribed_base' => ['required', 'integer', 'min:1'],
        ]);

        $prescription = $prescriptions->create($this->branchId($request), $this->header($data), [[
            'product_id' => (int) $data['product_id'],
            'quantity_prescribed_base' => (int) $data['quantity_prescribed_base'],
        ]], $this->user($request));

        return response()->json(['prescription_line_id' => $prescription->lines->firstOrFail()->id, 'number' => $prescription->number], 201);
    }

    /**
     * Other brands with the same generic name, cheapest per base unit first,
     * for the Generics Act prompt at the POS.
     */
    public function alternatives(Request $request, StockService $stock): JsonResponse
    {
        $product = Product::query()->findOrFail($request->integer('product_id'));
        $branchId = $this->branchId($request);

        $alternatives = Product::query()
            ->with(['genericName', 'dosageForm', 'units'])
            ->where('generic_name_id', $product->generic_name_id)
            ->whereKeyNot($product->id)
            ->where('is_active', true)
            ->get()
            ->map(function (Product $alternative) use ($stock, $branchId, $product) {
                $base = $alternative->units->firstWhere('is_base', true);

                return [
                    'id' => $alternative->id,
                    'name' => $alternative->displayName(),
                    'same_strength' => $alternative->strength === $product->strength,
                    'base_unit_id' => $base?->id,
                    'base_unit' => $base?->name,
                    'base_price' => $base?->price,
                    'available' => $stock->sellableOnHand($branchId, $alternative->id),
                ];
            })
            ->filter(fn (array $alternative) => $alternative['base_price'] !== null)
            ->sortBy(fn (array $alternative) => (float) $alternative['base_price'])
            ->values();

        return response()->json(['data' => $alternatives]);
    }

    /**
     * @return array<string, mixed>
     */
    private function headerRules(): array
    {
        return [
            'customer_id' => ['nullable', 'integer', Rule::exists('customers', 'id')],
            'patient_name' => ['required', 'string', 'max:150'],
            'patient_address' => ['nullable', 'string', 'max:255'],
            'prescriber_id' => ['required', 'integer', Rule::exists('prescribers', 'id')],
            'prescribed_on' => ['required', 'date', 'before_or_equal:today'],
            'special_form_serial' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{customer_id: int|null, patient_name: string, patient_address: string|null, prescriber_id: int, prescribed_on: string, special_form_serial: string|null, notes: string|null}
     */
    private function header(array $data): array
    {
        return [
            'customer_id' => isset($data['customer_id']) ? (int) $data['customer_id'] : null,
            'patient_name' => (string) $data['patient_name'],
            'patient_address' => $data['patient_address'] ?? null,
            'prescriber_id' => (int) $data['prescriber_id'],
            'prescribed_on' => (string) $data['prescribed_on'],
            'special_form_serial' => $data['special_form_serial'] ?? null,
            'notes' => $data['notes'] ?? null,
        ];
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }

    private function branchId(Request $request): int
    {
        $branchId = $this->user($request)->branch_id;
        abort_if($branchId === null, 403);

        return $branchId;
    }
}
