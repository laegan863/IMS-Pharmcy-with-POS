<?php

namespace App\Services\Sales;

use App\Enums\Permission;
use App\Enums\StockMovementType;
use App\Enums\TaxClass;
use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\Sale;
use App\Models\Shift;
use App\Models\Terminal;
use App\Models\User;
use App\Services\Compliance\DispensingRules;
use App\Services\DocumentNumbers;
use App\Services\Stock\InsufficientStockException;
use App\Services\Stock\StockService;
use App\Settings\Settings;
use App\Support\Decimal;
use App\Support\SupervisorApproval;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Prices and posts sales. The POS sends what was scanned (product, unit,
 * quantity), the discount type and the payments; this service decides every
 * amount. Posting runs in one transaction: lock and allocate stock first
 * expiry first out, write the invoice with its batches and payments, take the
 * invoice number and add to the terminal's grand total. If any step fails
 * nothing is saved and no number is used.
 *
 * Input line: {product_id, product_unit_id, quantity, unit_price?, batch_id?}
 * where unit_price is a price override and batch_id a batch override; both
 * need a supervisor approval sent with the sale.
 */
class SaleService
{
    public function __construct(
        private PriceCalculator $calculator,
        private StockService $stock,
        private DocumentNumbers $numbers,
        private Settings $settings,
        private DispensingRules $dispensing,
    ) {}

    /**
     * Prices a cart without saving anything, for the POS screen.
     *
     * @param  list<array<string, mixed>>  $lines
     * @return array{lines: list<array<string, mixed>>, totals: array<string, string>}
     */
    public function quote(int $branchId, array $lines, string $discountType): array
    {
        $resolved = $this->resolve($lines);
        $priced = $this->calculator->calculate($this->calculatorLines($resolved), $discountType);

        $out = [];

        foreach ($resolved as $index => $line) {
            $out[] = [
                ...$priced['lines'][$index],
                'product_id' => $line['product']->id,
                'product_unit_id' => $line['unit']->id,
                'description' => $line['description'],
                'quantity' => $line['quantity'],
                'unit_price' => $line['unit_price'],
                'list_price' => $line['list_price'],
                'classification' => $line['product']->classification->value,
                'available' => $this->stock->sellableOnHand($branchId, $line['product']->id),
                'quantity_base' => $line['quantity_base'],
            ];
        }

        return ['lines' => $out, 'totals' => $priced['totals']];
    }

    /**
     * Posts a sale. Calling it again with the same client_uuid returns the
     * sale already posted, so a retry after a dropped connection is safe.
     *
     * @param  array{client_uuid: string, lines: list<array<string, mixed>>, discount_type: string, discount_id_number?: string|null, discount_holder_name?: string|null, customer_id?: int|null, payments: list<array{method: string, amount: string, reference?: string|null}>, approver?: string|null, approver_pin?: string|null}  $input
     */
    public function complete(array $input, User $cashier, Shift $shift): Sale
    {
        $existing = Sale::withoutGlobalScopes()->where('client_uuid', $input['client_uuid'])->first();

        if ($existing !== null) {
            return $existing;
        }

        if (! $shift->isOpen() || $shift->user_id !== $cashier->id) {
            throw ValidationException::withMessages(['shift' => __('Open a shift before selling.')]);
        }

        $this->validateDiscount($input);
        $resolved = $this->resolve($input['lines']);
        $this->authorizeOverrides($resolved, $input);
        $pharmacist = $this->dispensing->check($resolved, $input, $cashier);

        $priced = $this->calculator->calculate($this->calculatorLines($resolved), $input['discount_type']);
        $total = $priced['totals']['line_total'];
        [$payments, $paid, $change] = $this->settlePayments($input['payments'], $total);

        try {
            return DB::transaction(function () use ($input, $cashier, $shift, $resolved, $priced, $payments, $paid, $change, $pharmacist): Sale {
                $terminal = Terminal::withoutGlobalScopes()->lockForUpdate()->findOrFail($shift->terminal_id);

                $sale = Sale::query()->create([
                    'branch_id' => $shift->branch_id,
                    'terminal_id' => $terminal->id,
                    'shift_id' => $shift->id,
                    'user_id' => $cashier->id,
                    'number' => $this->numbers->next(DocumentNumbers::SALES_INVOICE, $shift->branch_id, $terminal->id),
                    'client_uuid' => $input['client_uuid'],
                    'status' => 'completed',
                    'customer_id' => $input['customer_id'] ?? null,
                    'discount_type' => $input['discount_type'],
                    'discount_id_number' => $input['discount_id_number'] ?? null,
                    'discount_holder_name' => $input['discount_holder_name'] ?? null,
                    'gross_amount' => $priced['totals']['gross_amount'],
                    'vatable_sales' => $priced['totals']['vatable_amount'],
                    'vat_amount' => $priced['totals']['vat_amount'],
                    'vat_exempt_sales' => $priced['totals']['vat_exempt_amount'],
                    'zero_rated_sales' => $priced['totals']['zero_rated_amount'],
                    'statutory_discount' => $priced['totals']['discount_amount'],
                    'total' => $priced['totals']['line_total'],
                    'amount_paid' => $paid,
                    'change_due' => $change,
                    'completed_at' => now(),
                ]);

                $costs = [];

                foreach ($resolved as $index => $line) {
                    $amounts = $priced['lines'][$index];

                    $saleLine = $sale->lines()->create([
                        'product_id' => $line['product']->id,
                        'product_unit_id' => $line['unit']->id,
                        'description' => $line['description'],
                        'quantity' => $line['quantity'],
                        'quantity_base' => $line['quantity_base'],
                        'unit_price' => $line['unit_price'],
                        'list_price' => $line['list_price'],
                        'price_override_by' => $line['override_approver'],
                        'tax_class' => $line['product']->tax_class,
                        'statutory_discount_applied' => $amounts['statutory_discount_applied'],
                        'gross_amount' => $amounts['gross_amount'],
                        'vatable_amount' => $amounts['vatable_amount'],
                        'vat_amount' => $amounts['vat_amount'],
                        'vat_exempt_amount' => $amounts['vat_exempt_amount'],
                        'zero_rated_amount' => $amounts['zero_rated_amount'],
                        'discount_amount' => $amounts['discount_amount'],
                        'line_total' => $amounts['line_total'],
                    ]);

                    $allocations = $this->stock->issue(
                        $shift->branch_id,
                        $line['product'],
                        $line['quantity_base'],
                        StockMovementType::Sale,
                        $sale,
                        $sale->number,
                        $line['batch_id'],
                    );

                    $lineCost = [];

                    foreach ($allocations as $allocation) {
                        $saleLine->batches()->create([
                            'batch_id' => $allocation->batchId,
                            'quantity_base' => $allocation->quantity,
                            'unit_cost' => $allocation->unitCost,
                        ]);
                        $lineCost[] = Decimal::mul($allocation->quantity, $allocation->unitCost, Decimal::COST);
                    }

                    $saleLine->update(['cost_total' => Decimal::sum($lineCost, Decimal::COST)]);
                    $this->dispensing->record($saleLine, $line, $shift->branch_id, $pharmacist);
                    $costs[] = Decimal::sum($lineCost, Decimal::COST);
                }

                foreach ($payments as $payment) {
                    $sale->payments()->create([...$payment, 'created_at' => now()]);
                }

                $sale->update(['cost_total' => Decimal::sum($costs, Decimal::COST)]);

                $terminal->grand_total = Decimal::add($terminal->grand_total, $sale->total);
                $terminal->save();

                return $sale->refresh();
            });
        } catch (InsufficientStockException $exception) {
            throw ValidationException::withMessages([
                'lines' => __('Not enough stock of :product: :available available.', ['product' => $exception->productName, 'available' => $exception->available]),
            ]);
        } catch (UniqueConstraintViolationException $exception) {
            // A retry of the same cart raced the first attempt and lost: the
            // sale exists, so return it rather than posting twice.
            $posted = Sale::withoutGlobalScopes()->where('client_uuid', $input['client_uuid'])->first();

            if ($posted === null) {
                throw $exception;
            }

            return $posted;
        }
    }

    /**
     * @param  list<array<string, mixed>>  $lines
     * @return list<array{product: Product, unit: ProductUnit, quantity: int, quantity_base: int, unit_price: string, list_price: string, description: string, override: bool, batch_id: int|null, override_approver: int|null, prescription_line_id: int|null}>
     */
    private function resolve(array $lines): array
    {
        if ($lines === []) {
            throw ValidationException::withMessages(['lines' => __('The cart is empty.')]);
        }

        /** @var Collection<int, Product> $products */
        $products = Product::query()
            ->with(['units', 'genericName', 'dosageForm'])
            ->whereIn('id', array_map(fn (array $line) => (int) ($line['product_id'] ?? 0), $lines))
            ->get()
            ->keyBy('id');

        $resolved = [];

        foreach ($lines as $index => $line) {
            $product = $products->get((int) ($line['product_id'] ?? 0));
            $unit = $product?->units->firstWhere('id', (int) ($line['product_unit_id'] ?? 0));
            $quantity = (int) ($line['quantity'] ?? 0);

            if ($product === null || ! $product->is_active || $unit === null || ! $unit->is_sellable) {
                throw ValidationException::withMessages(["lines.{$index}" => __('This item cannot be sold.')]);
            }

            if ($quantity < 1) {
                throw ValidationException::withMessages(["lines.{$index}.quantity" => __('Quantity must be at least 1.')]);
            }

            if ($unit->price === null) {
                throw ValidationException::withMessages(["lines.{$index}" => __(':product has no price for :unit.', ['product' => $product->displayName(), 'unit' => $unit->name])]);
            }

            $override = isset($line['unit_price']) && $line['unit_price'] !== '' && Decimal::compare((string) $line['unit_price'], $unit->price) !== 0;

            $resolved[] = [
                'product' => $product,
                'unit' => $unit,
                'quantity' => $quantity,
                'quantity_base' => $unit->toBase($quantity),
                'unit_price' => $override ? Decimal::round((string) $line['unit_price']) : $unit->price,
                'list_price' => $unit->price,
                // "Biogesic (Paracetamol) 500 mg Tablet / Box"; the unit is left off when it repeats the dosage form.
                'description' => mb_strtolower($unit->name) === mb_strtolower((string) $product->dosageForm?->name)
                    ? $product->displayName()
                    : $product->displayName().' / '.$unit->name,
                'override' => $override,
                'batch_id' => isset($line['batch_id']) && $line['batch_id'] !== '' ? (int) $line['batch_id'] : null,
                'override_approver' => null,
                'prescription_line_id' => isset($line['prescription_line_id']) && $line['prescription_line_id'] !== '' ? (int) $line['prescription_line_id'] : null,
            ];
        }

        return $resolved;
    }

    /**
     * Price and batch overrides need a supervisor's PIN.
     *
     * @param  list<array<string, mixed>>  $resolved
     * @param  array<string, mixed>  $input
     */
    private function authorizeOverrides(array &$resolved, array $input): void
    {
        $priceOverride = collect($resolved)->contains(fn (array $line) => $line['override']);
        $batchOverride = collect($resolved)->contains(fn (array $line) => $line['batch_id'] !== null);

        if (! $priceOverride && ! $batchOverride) {
            return;
        }

        $approver = null;

        if ($priceOverride && $this->settings->bool('pos.supervisor_for_price_override')) {
            $approver = SupervisorApproval::verify($input['approver'] ?? null, $input['approver_pin'] ?? null, Permission::PosPriceOverride);
        }

        if ($batchOverride) {
            $approver = SupervisorApproval::verify($input['approver'] ?? null, $input['approver_pin'] ?? null, Permission::PosBatchOverride);
        }

        foreach ($resolved as &$line) {
            if ($line['override']) {
                $line['override_approver'] = $approver?->id;
            }
        }
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function validateDiscount(array $input): void
    {
        if (! in_array($input['discount_type'], [PriceCalculator::NONE, PriceCalculator::SENIOR, PriceCalculator::PWD], true)) {
            throw ValidationException::withMessages(['discount_type' => __('Choose a valid discount.')]);
        }

        if ($input['discount_type'] === PriceCalculator::NONE) {
            return;
        }

        if (blank($input['discount_id_number'] ?? null) || blank($input['discount_holder_name'] ?? null)) {
            throw ValidationException::withMessages(['discount_id_number' => __('Enter the ID number and name of the senior citizen or PWD.')]);
        }

        if (isset($input['customer_id']) && Customer::query()->whereKey($input['customer_id'])->doesntExist()) {
            throw ValidationException::withMessages(['customer_id' => __('Unknown customer.')]);
        }
    }

    /**
     * Payments must cover the total. Only cash can exceed what is due; the
     * difference is the change.
     *
     * @param  list<array{method: string, amount: string, reference?: string|null}>  $payments
     * @return array{0: list<array{method: string, amount: string, reference: string|null}>, 1: string, 2: string}
     */
    private function settlePayments(array $payments, string $total): array
    {
        $clean = [];
        $nonCash = '0.00';
        $paid = '0.00';

        foreach ($payments as $index => $payment) {
            $method = $payment['method'];
            $amount = Decimal::round($payment['amount']);

            if (! in_array($method, ['cash', 'ewallet', 'card'], true) || Decimal::compare($amount, '0') <= 0) {
                throw ValidationException::withMessages(["payments.{$index}.amount" => __('Enter a valid payment.')]);
            }

            if ($method !== 'cash' && blank($payment['reference'] ?? null)) {
                throw ValidationException::withMessages(["payments.{$index}.reference" => __('Enter the reference number for card and e-wallet payments.')]);
            }

            if ($method !== 'cash') {
                $nonCash = Decimal::add($nonCash, $amount);
            }

            $paid = Decimal::add($paid, $amount);
            $clean[] = ['method' => $method, 'amount' => $amount, 'reference' => $payment['reference'] ?? null];
        }

        if (Decimal::compare($nonCash, $total) > 0) {
            throw ValidationException::withMessages(['payments' => __('Card and e-wallet payments cannot be more than the total.')]);
        }

        if (Decimal::compare($paid, $total) < 0) {
            throw ValidationException::withMessages(['payments' => __('Payment is short by :amount.', ['amount' => Decimal::sub($total, $paid)])]);
        }

        return [$clean, $paid, Decimal::sub($paid, $total)];
    }

    /**
     * @param  list<array<string, mixed>>  $resolved
     * @return list<array{unit_price: string, quantity: int, tax_class: TaxClass, discount_eligible: bool}>
     */
    private function calculatorLines(array $resolved): array
    {
        return array_map(fn (array $line) => [
            'unit_price' => (string) $line['unit_price'],
            'quantity' => (int) $line['quantity'],
            'tax_class' => $line['product']->tax_class,
            'discount_eligible' => (bool) $line['product']->statutory_discount_eligible,
        ], $resolved);
    }
}
