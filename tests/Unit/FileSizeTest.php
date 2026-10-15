<?php

namespace Tests\Unit;

use App\Support\FileSize;
use PHPUnit\Framework\TestCase;

class FileSizeTest extends TestCase
{
    public function test_small_files_are_counted_in_whole_kilobytes_rounded_up(): void
    {
        $this->assertSame('1 كيلوبايت', FileSize::arabic(0));
        $this->assertSame('1 كيلوبايت', FileSize::arabic(1));
        $this->assertSame('1 كيلوبايت', FileSize::arabic(1024));
        $this->assertSame('2 كيلوبايت', FileSize::arabic(1025));
        $this->assertSame('850 كيلوبايت', FileSize::arabic(850 * 1024));
        $this->assertSame('1024 كيلوبايت', FileSize::arabic(1024 * 1024 - 1));
    }

    public function test_larger_files_are_counted_in_megabytes_with_one_decimal_when_it_says_something(): void
    {
        $this->assertSame('1 ميغابايت', FileSize::arabic(1024 * 1024));
        $this->assertSame('3.4 ميغابايت', FileSize::arabic((int) (3.4 * 1024 * 1024)));
        $this->assertSame('10 ميغابايت', FileSize::arabic(10240 * 1024));
        $this->assertSame('500 ميغابايت', FileSize::arabic(512000 * 1024));
        $this->assertSame('1536.5 ميغابايت', FileSize::arabic((int) (1536.5 * 1024 * 1024)));
    }
}
