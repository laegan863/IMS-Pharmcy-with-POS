<?php

namespace Tests\Feature\Sales;

use App\Enums\TaxClass;
use App\Services\Sales\PriceCalculator;
use App\Settings\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The statutory discount and VAT rules, tested to the centavo. The first
 * test is the official worked example from the development plan.
 */
class PriceCalculatorTest extends TestCase
{
    use RefreshDatabase;

    private function calc(): PriceCalculator
    {
        return app(PriceCalculator::class);
    }

    /**
     * @return array{unit_price: string, quantity: int, tax_class: TaxClass, discount_eligible: bool}
     */
    private function line(string $price, int $quantity = 1, TaxClass $taxClass = TaxClass::Vatable, bool $eligible = true): array
    {
        return ['unit_price' => $price, 'quantity' => $quantity, 'tax_class' => $taxClass, 'discount_eligible' => $eligible];
    }

    public function test_official_example_senior_on_a_vatable_medicine()
    {
        $result = $this->calc()->calculate([$this->line('100.00')], PriceCalculator::SENIOR);
        $line = $result['lines'][0];

        $this->assertSame('89.29', $line['vat_exempt_amount']);
        $this->assertSame('17.86', $line['discount_amount']);
        $this->assertSame('71.43', $line['line_total']);
        $this->assertSame('0.00', $line['vat_amount']);
        $this->assertTrue($line['statutory_discount_applied']);
    }

    public function test_pwd_gets_the_same_computation()
    {
        $line = $this->calc()->calculate([$this->line('100.00')], PriceCalculator::PWD)['lines'][0];

        $this->assertSame('71.43', $line['line_total']);
    }

    public function test_a_vat_exempt_medicine_takes_20_percent_off_the_price()
    {
        $line = $this->calc()->calculate([$this->line('100.00', taxClass: TaxClass::VatExempt)], PriceCalculator::SENIOR)['lines'][0];

        $this->assertSame('20.00', $line['discount_amount']);
        $this->assertSame('80.00', $line['line_total']);
        $this->assertSame('100.00', $line['vat_exempt_amount']);
    }

    public function test_a_regular_sale_splits_vat_out_of_the_inclusive_price()
    {
        $result = $this->calc()->calculate([$this->line('5.50', 10)]);
        $line = $result['lines'][0];

        // 55.00 ÷ 1.12 = 49.107… → 49.11; VAT 5.89
        $this->assertSame('55.00', $line['gross_amount']);
        $this->assertSame('49.11', $line['vatable_amount']);
        $this->assertSame('5.89', $line['vat_amount']);
        $this->assertSame('55.00', $line['line_total']);
    }

    public function test_ineligible_items_keep_vat_on_a_senior_sale()
    {
        $result = $this->calc()->calculate([
            $this->line('100.00'),
            $this->line('89.00', eligible: false),
        ], PriceCalculator::SENIOR);

        $this->assertSame('71.43', $result['lines'][0]['line_total']);
        $this->assertFalse($result['lines'][1]['statutory_discount_applied']);
        $this->assertSame('89.00', $result['lines'][1]['line_total']);
        $this->assertSame('9.54', $result['lines'][1]['vat_amount']);

        $totals = $result['totals'];
        $this->assertSame('160.43', $totals['line_total']);
        $this->assertSame('17.86', $totals['discount_amount']);
        $this->assertSame('89.29', $totals['vat_exempt_amount']);
        $this->assertSame('79.46', $totals['vatable_amount']);
        $this->assertSame('9.54', $totals['vat_amount']);
    }

    public function test_the_breakdown_always_adds_up_to_the_total()
    {
        $result = $this->calc()->calculate([
            $this->line('5.50', 7),
            $this->line('18.00', 3, TaxClass::VatExempt),
            $this->line('165.00', 1),
            $this->line('33.33', 3, TaxClass::ZeroRated),
            $this->line('89.00', 2, eligible: false),
        ], PriceCalculator::SENIOR);

        foreach ($result['lines'] as $line) {
            $parts = bcadd(bcadd(bcadd((string) $line['vatable_amount'], (string) $line['vat_amount'], 2), (string) $line['vat_exempt_amount'], 2), (string) $line['zero_rated_amount'], 2);
            $this->assertSame((string) $line['line_total'], bcsub($parts, (string) $line['discount_amount'], 2));
        }
    }

    public function test_a_non_vat_store_charges_no_vat_and_discounts_the_full_price()
    {
        app(Settings::class)->set(['store.vat_registered' => false]);

        $line = $this->calc()->calculate([$this->line('100.00')], PriceCalculator::SENIOR)['lines'][0];

        $this->assertSame('0.00', $line['vat_amount']);
        $this->assertSame('80.00', $line['line_total']);
    }

    public function test_prices_entered_without_vat_have_it_added()
    {
        app(Settings::class)->set(['tax.prices_include_vat' => false]);

        $line = $this->calc()->calculate([$this->line('100.00')])['lines'][0];

        $this->assertSame('112.00', $line['line_total']);
        $this->assertSame('12.00', $line['vat_amount']);
    }

    public function test_unknown_discount_types_are_rejected()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->calc()->calculate([$this->line('1.00')], 'loyalty');
    }
}
