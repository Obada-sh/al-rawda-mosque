<?php

namespace App\Support;

use App\Exceptions\BusinessRuleViolation;

/**
 * Exact money arithmetic for expenses, done in integers (no floats, no bcmath).
 *
 * Every amount is in Syrian pounds. Quantities carry 3 decimals, prices and
 * totals carry 2.
 */
final class Money
{
    /** The currency of every amount: the Syrian pound. */
    public const CURRENCY = 'ل.س';

    /** Largest total that fits DECIMAL(16,2), in cents. */
    private const MAX_TOTAL_CENTS = 9_999_999_999_999_999;

    /**
     * quantity × unit price, rounded half up to 2 decimals.
     *
     * @return string decimal string such as "1250.50"
     */
    public static function lineTotal(string|int|float $quantity, string|int|float $unitPrice): string
    {
        $thousandths = self::toScaledInt($quantity, 3);
        $cents = self::toScaledInt($unitPrice, 2);

        $whole = intdiv($thousandths, 1000);
        $fraction = $thousandths % 1000;

        if ($whole > 0 && $cents > intdiv(self::MAX_TOTAL_CENTS, $whole)) {
            throw BusinessRuleViolation::because('expense.total_too_large', 'إجمالي المصروف أكبر من الحد المسموح.');
        }

        $totalCents = ($cents * $whole) + intdiv(($cents * $fraction) + 500, 1000);

        if ($totalCents > self::MAX_TOTAL_CENTS) {
            throw BusinessRuleViolation::because('expense.total_too_large', 'إجمالي المصروف أكبر من الحد المسموح.');
        }

        return self::fromCents($totalCents);
    }

    /**
     * Adds amounts exactly.
     *
     * @return string decimal string such as "1250.50"
     */
    public static function sum(string|int|float ...$amounts): string
    {
        $cents = 0;

        foreach ($amounts as $amount) {
            $cents += self::toScaledInt($amount, 2);
        }

        return self::fromCents($cents);
    }

    /**
     * An amount as it is written for the admin: "1,250" or "1,250.50" (the
     * piastres only when there are some).
     */
    public static function format(string|int|float $amount): string
    {
        [$whole, $fraction] = explode('.', self::fromCents(self::toScaledInt($amount, 2)));

        $whole = strrev(implode(',', str_split(strrev($whole), 3)));

        return $fraction === '00' ? $whole : $whole.'.'.$fraction;
    }

    /**
     * An amount with its currency: "1,250 ل.س".
     */
    public static function pounds(string|int|float $amount): string
    {
        return self::format($amount).' '.self::CURRENCY;
    }

    /**
     * The part of a total that an amount makes, as a percentage with one
     * decimal. Null when the total is zero.
     */
    public static function share(string|int|float $amount, string|int|float $total): ?float
    {
        $totalCents = self::toScaledInt($total, 2);

        if ($totalCents === 0) {
            return null;
        }

        return round(self::toScaledInt($amount, 2) * 100 / $totalCents, 1);
    }

    /**
     * Converts a non-negative decimal to an integer scaled by 10^$scale.
     */
    private static function toScaledInt(string|int|float $value, int $scale): int
    {
        $normalized = is_string($value) ? trim($value) : number_format((float) $value, $scale, '.', '');

        // Plain decimals only ("12", "12.5", ".5"); no sign and no exponent.
        if (preg_match('/^(\d+\.?\d*|\.\d+)$/', $normalized) !== 1) {
            throw BusinessRuleViolation::because('money.invalid_number', 'قيمة رقمية غير صالحة.');
        }

        [$integer, $decimals] = array_pad(explode('.', $normalized, 2), 2, '');
        $integer = $integer === '' ? '0' : $integer;

        // Extra decimals are rounded half up at the target scale.
        $kept = substr(str_pad($decimals, $scale, '0'), 0, $scale);
        $roundUp = strlen($decimals) > $scale && (int) $decimals[$scale] >= 5;

        return (int) ($integer.$kept) + ($roundUp ? 1 : 0);
    }

    private static function fromCents(int $cents): string
    {
        return intdiv($cents, 100).'.'.str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
    }
}
