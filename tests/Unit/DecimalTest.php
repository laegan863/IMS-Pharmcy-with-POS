<?php

namespace Tests\Unit;

use App\Support\Decimal;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class DecimalTest extends TestCase
{
    public function test_rounding_is_half_away_from_zero()
    {
        $this->assertSame('0.01', Decimal::round('0.005'));
        $this->assertSame('-0.01', Decimal::round('-0.005'));
        $this->assertSame('0.00', Decimal::round('0.0049'));
    }

    public function test_division_keeps_four_decimals_for_costs()
    {
        $this->assertSame('33.3333', Decimal::div('1000', 30, Decimal::COST));
        $this->assertSame('4.5500', Decimal::div('455', 100, Decimal::COST));
    }

    public function test_money_arithmetic_is_exact()
    {
        // 0.1 + 0.2 is 0.30000000000000004 as a float.
        $this->assertSame('0.30', Decimal::add('0.1', '0.2'));
        $this->assertSame('89.29', Decimal::div('100', '1.12'));
        $this->assertSame('17.86', Decimal::mul('89.29', '0.20'));
        $this->assertSame('3.40', Decimal::sum(['1.10', '1.10', '1.20']));
    }

    public function test_division_by_zero_returns_zero()
    {
        $this->assertSame('0.00', Decimal::div('5', 0));
    }

    public function test_non_numbers_are_rejected()
    {
        $this->expectException(InvalidArgumentException::class);
        Decimal::add('abc', '1');
    }
}
