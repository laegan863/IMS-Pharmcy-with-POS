<?php

namespace App\Services\Sales;

use App\Enums\TaxClass;
use App\Settings\Settings;
use App\Support\Decimal;
use InvalidArgumentException;

/**
 * Computes every amount on a sale: VAT, the senior citizen / PWD discount,
 * and the VATable / VAT-exempt / zero-rated breakdown printed on the invoice.
 * The POS screen only shows what this returns; the client never computes
 * prices.
 *
 * Order of computation for a senior citizen or PWD on a VATable medicine
 * (DOH guidelines, with VAT exemption on):
 *   1. remove VAT:      100.00 ÷ 1.12 = 89.29
 *   2. take 20% off:     89.29 × 0.20 = 17.86
 *   3. customer pays:    89.29 − 17.86 = 71.43
 * A VAT-exempt or zero-rated medicine takes the 20% straight off the price.
 * Items not eligible (most non-drug items) keep their normal VAT.
 * Only one statutory discount per sale: a person who is both a senior and a
 * PWD chooses one, and promos do not stack on it.
 *
 * Every amount is rounded per line, half away from zero (see Decimal).
 */
class PriceCalculator
{
    public const NONE = 'none';

    public const SENIOR = 'senior';

    public const PWD = 'pwd';

    public function __construct(private Settings $settings) {}

    /**
     * @param  list<array{unit_price: string, quantity: int, tax_class: TaxClass, discount_eligible: bool}>  $lines
     * @return array{lines: list<array<string, string|bool>>, totals: array<string, string>}
     */
    public function calculate(array $lines, string $discountType = self::NONE): array
    {
        if (! in_array($discountType, [self::NONE, self::SENIOR, self::PWD], true)) {
            throw new InvalidArgumentException("Unknown discount type [{$discountType}].");
        }

        $vatRegistered = $this->settings->bool('store.vat_registered');
        $vatFactor = Decimal::add('1', Decimal::div((string) $this->settings->get('tax.vat_rate'), '100', 6), 6);
        $pricesIncludeVat = $this->settings->bool('tax.prices_include_vat');
        $discountRate = Decimal::div((string) $this->settings->get('discounts.statutory_rate'), '100', 6);
        $statutoryVatExempt = $this->settings->bool('discounts.statutory_vat_exempt');

        $results = [];

        foreach ($lines as $line) {
            $results[] = $this->line(
                $line,
                $discountType !== self::NONE && $line['discount_eligible'],
                $vatRegistered,
                $vatFactor,
                $pricesIncludeVat,
                $discountRate,
                $statutoryVatExempt,
            );
        }

        $totals = [];

        foreach (['gross_amount', 'vatable_amount', 'vat_amount', 'vat_exempt_amount', 'zero_rated_amount', 'discount_amount', 'line_total'] as $key) {
            $totals[$key] = Decimal::sum(array_map(fn (array $result) => (string) $result[$key], $results));
        }

        return ['lines' => $results, 'totals' => $totals];
    }

    /**
     * @param  array{unit_price: string, quantity: int, tax_class: TaxClass, discount_eligible: bool}  $line
     * @return array<string, string|bool>
     */
    private function line(array $line, bool $applyDiscount, bool $vatRegistered, string $vatFactor, bool $pricesIncludeVat, string $discountRate, bool $statutoryVatExempt): array
    {
        $taxClass = $line['tax_class'];
        $gross = Decimal::mul($line['unit_price'], $line['quantity']);

        if ($vatRegistered && $taxClass === TaxClass::Vatable && ! $pricesIncludeVat) {
            $gross = Decimal::mul($gross, $vatFactor);
        }

        $zero = '0.00';
        $result = [
            'gross_amount' => $gross,
            'vatable_amount' => $zero,
            'vat_amount' => $zero,
            'vat_exempt_amount' => $zero,
            'zero_rated_amount' => $zero,
            'discount_amount' => $zero,
            'line_total' => $gross,
            'statutory_discount_applied' => $applyDiscount,
        ];

        // Non-VAT-registered stores charge no VAT at all.
        if (! $vatRegistered || $taxClass === TaxClass::VatExempt) {
            $discount = $applyDiscount ? Decimal::mul($gross, $discountRate) : $zero;

            return [...$result, 'vat_exempt_amount' => $gross, 'discount_amount' => $discount, 'line_total' => Decimal::sub($gross, $discount)];
        }

        if ($taxClass === TaxClass::ZeroRated) {
            $discount = $applyDiscount ? Decimal::mul($gross, $discountRate) : $zero;

            return [...$result, 'zero_rated_amount' => $gross, 'discount_amount' => $discount, 'line_total' => Decimal::sub($gross, $discount)];
        }

        // VATable from here on.
        if ($applyDiscount && $statutoryVatExempt) {
            $base = Decimal::div($gross, $vatFactor);
            $discount = Decimal::mul($base, $discountRate);

            return [...$result, 'vat_exempt_amount' => $base, 'discount_amount' => $discount, 'line_total' => Decimal::sub($base, $discount)];
        }

        $discount = $applyDiscount ? Decimal::mul($gross, $discountRate) : $zero;
        $total = Decimal::sub($gross, $discount);
        $vatable = Decimal::div($total, $vatFactor);

        return [
            ...$result,
            'vatable_amount' => $vatable,
            'vat_amount' => Decimal::sub($total, $vatable),
            'discount_amount' => $discount,
            'line_total' => $total,
        ];
    }
}
