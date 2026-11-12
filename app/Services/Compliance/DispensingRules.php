<?php

namespace App\Services\Compliance;

use App\Enums\Permission;
use App\Enums\ProductClassification;
use App\Models\DangerousDrugEntry;
use App\Models\PrescriptionLine;
use App\Models\SaleLine;
use App\Models\User;
use App\Services\Stock\StockService;
use App\Settings\Settings;
use App\Support\SupervisorApproval;
use Illuminate\Validation\ValidationException;

/**
 * Pharmacy rules applied when a sale is posted:
 *  - Rx items need a recorded prescription line with enough balance left
 *    (partial fills are allowed). A generic equivalent may fill it.
 *  - Dangerous drugs (S2) also need the special prescription form serial,
 *    an S2 prescriber and a pharmacist: the cashier must hold the
 *    dangerous-drugs.dispense permission or a pharmacist approves with PIN.
 *    Each dispensing is written to the dangerous drug register.
 */
class DispensingRules
{
    public function __construct(private Settings $settings, private StockService $stock) {}

    /**
     * Checks every line before posting. Returns the pharmacist who dispenses
     * any dangerous drug on the sale, or null if there is none.
     *
     * @param  list<array<string, mixed>>  $resolved
     * @param  array<string, mixed>  $input
     */
    public function check(array $resolved, array $input, User $cashier): ?User
    {
        $pharmacist = null;
        $requested = [];

        foreach ($resolved as $index => $line) {
            $classification = $line['product']->classification;

            if (! $classification->needsPrescription()) {
                continue;
            }

            if ($classification === ProductClassification::DangerousDrug && ! $this->settings->bool('prescription.dangerous_drugs_enabled')) {
                throw ValidationException::withMessages(["lines.{$index}" => __('This store is not set up to sell dangerous drugs.')]);
            }

            $prescriptionLineId = $line['prescription_line_id'];
            $mustHavePrescription = $classification === ProductClassification::DangerousDrug
                || $this->settings->bool('prescription.block_rx_without_prescription');

            if ($prescriptionLineId === null) {
                if ($mustHavePrescription) {
                    throw ValidationException::withMessages(["lines.{$index}" => __(':product needs a recorded prescription.', ['product' => $line['product']->displayName()])]);
                }

                continue;
            }

            $prescriptionLine = PrescriptionLine::query()->with(['prescription', 'product'])->find((int) $prescriptionLineId);

            if ($prescriptionLine === null || $prescriptionLine->product->generic_name_id !== $line['product']->generic_name_id) {
                throw ValidationException::withMessages(["lines.{$index}" => __('The prescription is not for this medicine.')]);
            }

            $requested[$prescriptionLineId] = ($requested[$prescriptionLineId] ?? 0) + $line['quantity_base'];

            if ($requested[$prescriptionLineId] > $prescriptionLine->balance()) {
                throw ValidationException::withMessages(["lines.{$index}.quantity" => __('Only :balance left to dispense on prescription :number.', ['balance' => $prescriptionLine->balance(), 'number' => $prescriptionLine->prescription->number])]);
            }

            if ($classification === ProductClassification::DangerousDrug) {
                if (blank($prescriptionLine->prescription->special_form_serial) || blank($prescriptionLine->prescription->prescriber_s2_number)) {
                    throw ValidationException::withMessages(["lines.{$index}" => __("Dangerous drugs need the special prescription form and the prescriber's S2 number.")]);
                }

                $pharmacist ??= $cashier->can(Permission::DangerousDrugsDispense->value)
                    ? $cashier
                    : SupervisorApproval::verify($input['approver'] ?? null, $input['approver_pin'] ?? null, Permission::DangerousDrugsDispense);
            }
        }

        return $pharmacist;
    }

    /**
     * Records the dispensing on the prescription and, for a dangerous drug,
     * in the register. Runs inside the posting transaction.
     *
     * @param  array<string, mixed>  $line
     */
    public function record(SaleLine $saleLine, array $line, int $branchId, ?User $pharmacist): void
    {
        if ($line['prescription_line_id'] === null) {
            return;
        }

        $prescriptionLine = PrescriptionLine::query()->with('prescription')->lockForUpdate()->findOrFail((int) $line['prescription_line_id']);

        if ($line['quantity_base'] > $prescriptionLine->balance()) {
            throw ValidationException::withMessages(['lines' => __('The prescription was filled at another counter; check its balance.')]);
        }

        $prescriptionLine->increment('quantity_dispensed_base', $line['quantity_base']);
        $saleLine->update(['prescription_line_id' => $prescriptionLine->id]);

        if ($line['product']->classification !== ProductClassification::DangerousDrug || $pharmacist === null) {
            return;
        }

        $prescription = $prescriptionLine->prescription;

        DangerousDrugEntry::query()->create([
            'branch_id' => $branchId,
            'sale_line_id' => $saleLine->id,
            'product_id' => $saleLine->product_id,
            'prescription_id' => $prescription->id,
            'special_form_serial' => (string) $prescription->special_form_serial,
            'patient_name' => $prescription->patient_name,
            'patient_address' => $prescription->patient_address,
            'prescriber_name' => $prescription->prescriber_name,
            'prescriber_s2_number' => (string) $prescription->prescriber_s2_number,
            'quantity_base' => $line['quantity_base'],
            'balance_after' => $this->stock->onHand($branchId, $saleLine->product_id),
            'pharmacist_id' => $pharmacist->id,
            'created_at' => now(),
        ]);
    }
}
