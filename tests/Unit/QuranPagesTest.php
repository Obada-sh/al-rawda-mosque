<?php

namespace Tests\Unit;

use App\Support\QuranPages;
use PHPUnit\Framework\TestCase;

class QuranPagesTest extends TestCase
{
    public function test_the_thirty_juz_cover_every_page_exactly_once(): void
    {
        $ranges = QuranPages::juzRanges();

        $this->assertCount(QuranPages::JUZ_COUNT, $ranges);
        $this->assertSame(QuranPages::FIRST, $ranges[0]['from_page']);
        $this->assertSame(QuranPages::LAST, $ranges[29]['to_page']);

        $expectedNextPage = QuranPages::FIRST;

        foreach ($ranges as $range) {
            $this->assertSame($expectedNextPage, $range['from_page'], "Juz {$range['juz_no']} does not start right after the previous one.");
            $this->assertGreaterThanOrEqual($range['from_page'], $range['to_page']);

            $expectedNextPage = $range['to_page'] + 1;
        }

        $this->assertSame(QuranPages::LAST + 1, $expectedNextPage);
    }

    public function test_page_bounds(): void
    {
        $this->assertTrue(QuranPages::isValidPage(1));
        $this->assertTrue(QuranPages::isValidPage(604));
        $this->assertFalse(QuranPages::isValidPage(0));
        $this->assertFalse(QuranPages::isValidPage(605));
    }
}
