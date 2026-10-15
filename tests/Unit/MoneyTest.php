<?php

namespace Tests\Unit;

use App\Exceptions\BusinessRuleViolation;
use App\Support\Money;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class MoneyTest extends TestCase
{
    /**
     * @return array<string, array{0: string|int|float, 1: string|int|float, 2: string}>
     */
    public static function lineTotals(): array
    {
        return [
            'whole numbers' => ['2', '10.50', '21.00'],
            'fractional quantity' => ['1.5', '3.33', '5.00'],
            'rounds half up' => ['0.333', '3', '1.00'],
            'leading dot' => ['.5', '9.99', '5.00'],
            'decimal strings from the database' => ['2.500', '1000.00', '2500.00'],
            'tiny amounts round down' => ['0.001', '0.49', '0.00'],
            'tiny amounts round up' => ['0.001', '500', '0.50'],
            'numbers instead of strings' => [2, 10.5, '21.00'],
            'free item' => ['7', '0', '0.00'],
            'largest price' => ['1', '999999999999.99', '999999999999.99'],
        ];
    }

    #[DataProvider('lineTotals')]
    public function test_line_total_is_exact(string|int|float $quantity, string|int|float $unitPrice, string $expected): void
    {
        $this->assertSame($expected, Money::lineTotal($quantity, $unitPrice));
    }

    public function test_a_total_too_large_for_the_database_is_refused(): void
    {
        $this->expectException(BusinessRuleViolation::class);

        Money::lineTotal('999999.999', '999999999999.99');
    }

    public function test_values_that_are_not_plain_decimals_are_refused(): void
    {
        $this->expectException(BusinessRuleViolation::class);

        Money::lineTotal('1e3', '5');
    }

    public function test_amounts_are_added_exactly(): void
    {
        $this->assertSame('3.35', Money::sum('1.10', '2.20', '0.05'));
        $this->assertSame('36000.00', Money::sum('30000.00', '6000'));
        $this->assertSame('0.00', Money::sum());
        // Floating-point numbers would give 0.30000000000000004 here.
        $this->assertSame('0.30', Money::sum('0.1', '0.2'));
    }

    public function test_an_amount_is_written_with_thousands_separators_and_piastres_only_when_there_are_some(): void
    {
        $this->assertSame('30,000', Money::format('30000.00'));
        $this->assertSame('1,234,567.80', Money::format('1234567.8'));
        $this->assertSame('999.99', Money::format('999.99'));
        $this->assertSame('0', Money::format('0'));
        $this->assertSame('12', Money::format(12));
        $this->assertSame('0.05', Money::format('0.05'));
    }

    public function test_every_amount_is_in_syrian_pounds(): void
    {
        $this->assertSame('ل.س', Money::CURRENCY);
        $this->assertSame('30,000 ل.س', Money::pounds('30000.00'));
        $this->assertSame('2,505.50 ل.س', Money::pounds(2505.5));
    }

    public function test_the_share_of_a_total_is_a_percentage_with_one_decimal(): void
    {
        $this->assertSame(25.0, Money::share('25', '100'));
        $this->assertSame(33.3, Money::share('1', '3'));
        $this->assertSame(0.0, Money::share('0', '10'));
        $this->assertNull(Money::share('5', '0'));
    }
}
