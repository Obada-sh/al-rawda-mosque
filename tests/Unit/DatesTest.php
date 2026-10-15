<?php

namespace Tests\Unit;

use App\Support\Dates;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

/**
 * Dates are written for the admin in numbers only: day / month / year.
 */
class DatesTest extends TestCase
{
    public function test_a_day_is_written_day_month_year_in_numbers(): void
    {
        $this->assertSame('07/10/2026', Dates::show('2026-10-07'));
        $this->assertSame('31/12/2026', Dates::show(CarbonImmutable::create(2026, 12, 31, 18, 30)));
        $this->assertSame('01/01/2027', Dates::show('2027-01-01 00:00:00'));
    }

    public function test_the_weekday_keeps_its_name_and_the_month_is_a_number(): void
    {
        $this->assertSame('الأربعاء 07/10/2026', Dates::showWithWeekday('2026-10-07'));
        $this->assertSame('السبت 10/10/2026', Dates::showWithWeekday('2026-10-10'));
    }

    public function test_a_month_is_written_month_year(): void
    {
        $this->assertSame('10/2026', Dates::showMonth(2026, 10));
        $this->assertSame('01/2027', Dates::showMonth(2027, 1));
    }
}
