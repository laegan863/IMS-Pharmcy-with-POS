<?php

namespace App\Services\Compliance;

use App\Models\Prescriber;
use App\Models\Prescription;
use App\Models\PrescriptionLine;
use App\Models\Product;
use App\Models\User;
use App\Services\DocumentNumbers;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Records prescriptions so Rx items can be sold. A dangerous drug line needs
 * the special prescription form serial and a prescriber with an S2 licence.
 */
class PrescriptionService
{
    public const SERIES = 'prescription';

    public function __construct(private DocumentNumbers $numbers) {}

    /**
     * @param  array{customer_id?: int|null, patient_name: string, patient_address?: string|null, prescriber_id: int, prescribed_on: string, special_form_serial?: string|null, notes?: string|null}  $header
     * @param  list<array{product_id: int, quantity_prescribed_base: int, directions?: string|null}>  $lines
     */
    public function create(int $branchId, array $header, array $lines, User $user, ?UploadedFile $photo = null): Prescription
    {
        $prescriber = Prescriber::query()->findOrFail($header['prescriber_id']);
        $products = Product::query()->whereIn('id', array_column($lines, 'product_id'))->get()->keyBy('id');

        foreach ($lines as $index => $line) {
            $product = $products->get($line['product_id']);

            if ($product === null) {
                throw ValidationException::withMessages(["lines.{$index}.product_id" => __('Choose a product.')]);
            }

            if ($product->classification->value === 'dangerous_drug') {
                if (! $prescriber->canPrescribeDangerousDrugs()) {
                    throw ValidationException::withMessages(['prescriber_id' => __('This prescriber has no S2 licence on file.')]);
                }

                if (blank($header['special_form_serial'] ?? null)) {
                    throw ValidationException::withMessages(['special_form_serial' => __('Enter the special prescription form serial number.')]);
                }
            }
        }

        return DB::transaction(function () use ($branchId, $header, $lines, $user, $photo): Prescription {
            $prescription = Prescription::query()->create([
                ...$header,
                'branch_id' => $branchId,
                'number' => $this->numbers->next(self::SERIES, $branchId),
                'photo_path' => $photo?->store('prescriptions', 'local'),
                'created_by' => $user->id,
            ]);

            foreach ($lines as $line) {
                $prescription->lines()->create($line);
            }

            return $prescription->load('lines');
        });
    }

    /**
     * Open prescription lines (balance left) for a product or its generic
     * equivalents in a branch, newest first.
     *
     * @return list<array<string, mixed>>
     */
    public function openLinesFor(int $branchId, Product $product): array
    {
        return array_values(PrescriptionLine::query()
            ->with(['prescription.prescriber', 'product.genericName'])
            ->whereHas('prescription', fn ($query) => $query->where('branch_id', $branchId))
            ->whereHas('product', fn ($query) => $query->where('generic_name_id', $product->generic_name_id))
            ->whereColumn('quantity_dispensed_base', '<', 'quantity_prescribed_base')
            ->latest('id')
            ->limit(20)
            ->get()
            ->map(fn (PrescriptionLine $line) => [
                'id' => $line->id,
                'number' => $line->prescription->number,
                'patient_name' => $line->prescription->patient_name,
                'prescriber' => $line->prescription->prescriber->name,
                'prescribed_on' => $line->prescription->prescribed_on->toDateString(),
                'product' => $line->product->displayName(),
                'balance' => $line->balance(),
                'special_form_serial' => $line->prescription->special_form_serial,
            ])
            ->all());
    }
}
