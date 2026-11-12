<?php

namespace App\Support;

use InvalidArgumentException;
use RoundingMode;

/**
 * Exact decimal arithmetic for money and costs, using bcmath on strings so
 * no value ever passes through a float.
 *
 * Rounding rule (the one documented rule for the whole system): results are
 * rounded half away from zero at the last kept digit, so 0.005 becomes 0.01
 * and -0.005 becomes -0.01. Money keeps 2 decimals; unit costs keep 4.
 */
final class Decimal
{
    public const MONEY = 2;

    public const COST = 4;

    /** Working precision for intermediate results before the final round. */
    private const WORK = 10;

    public static function add(string|int $a, string|int $b, int $scale = self::MONEY): string
    {
        return self::round(bcadd(self::of($a), self::of($b), self::WORK), $scale);
    }

    public static function sub(string|int $a, string|int $b, int $scale = self::MONEY): string
    {
        return self::round(bcsub(self::of($a), self::of($b), self::WORK), $scale);
    }

    public static function mul(string|int $a, string|int $b, int $scale = self::MONEY): string
    {
        return self::round(bcmul(self::of($a), self::of($b), self::WORK), $scale);
    }

    public static function div(string|int $a, string|int $b, int $scale = self::MONEY): string
    {
        if (bccomp(self::of($b), '0', self::WORK) === 0) {
            return self::round('0', $scale);
        }

        return self::round(bcdiv(self::of($a), self::of($b), self::WORK), $scale);
    }

    /**
     * Rounds half away from zero to the given number of decimals.
     */
    public static function round(string|int $value, int $scale = self::MONEY): string
    {
        return bcround(self::of($value), $scale, RoundingMode::HalfAwayFromZero);
    }

    public static function compare(string|int $a, string|int $b): int
    {
        return bccomp(self::of($a), self::of($b), self::WORK);
    }

    public static function isZero(string|int $value): bool
    {
        return self::compare($value, 0) === 0;
    }

    /**
     * Sum of many values.
     *
     * @param  iterable<string|int>  $values
     */
    public static function sum(iterable $values, int $scale = self::MONEY): string
    {
        $total = '0';

        foreach ($values as $value) {
            $total = bcadd($total, self::of($value), self::WORK);
        }

        return self::round($total, $scale);
    }

    /**
     * Normalises input such as "12", "12.5" or 12 to a bcmath string. Blank
     * counts as zero; anything else non-numeric is a programming error.
     *
     * @return numeric-string
     */
    private static function of(string|int $value): string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return '0';
        }

        if (! is_numeric($value)) {
            throw new InvalidArgumentException("Not a number: [{$value}].");
        }

        return $value;
    }
}
