<?php

namespace Tests\Unit;

use App\Support\UploadLimit;
use PHPUnit\Framework\TestCase;

class UploadLimitTest extends TestCase
{
    public function test_the_limit_of_the_system_stands_when_php_allows_more(): void
    {
        $this->assertSame(10240, UploadLimit::within(10240, '512M', '520M'));
        $this->assertSame(512000, UploadLimit::within(512000, '512M', '520M'));
        $this->assertSame(512000, UploadLimit::within(512000, '2G', '2G'));
    }

    public function test_the_smallest_limit_of_php_wins_when_it_is_lower(): void
    {
        // What a PHP that nobody configured allows.
        $this->assertSame(2048, UploadLimit::within(512000, '2M', '8M'));
        $this->assertSame(2048, UploadLimit::within(10240, '2M', '8M'));
        $this->assertSame(8192, UploadLimit::within(512000, '512M', '8M'));
        $this->assertSame(100, UploadLimit::within(512000, '102400', '8M'));
        $this->assertSame(300, UploadLimit::within(512000, ' 300K ', '8M'));
    }

    public function test_zero_means_that_php_sets_no_limit(): void
    {
        $this->assertSame(512000, UploadLimit::within(512000, '0', '0'));
        $this->assertSame(512000, UploadLimit::within(512000, '', '-1'));
        $this->assertSame(8192, UploadLimit::within(512000, '0', '8M'));
        $this->assertSame(512000, UploadLimit::within(512000));
    }

    public function test_the_limit_of_this_computer_never_exceeds_what_the_system_accepts(): void
    {
        $limit = UploadLimit::kilobytes(10240);

        $this->assertGreaterThan(0, $limit);
        $this->assertLessThanOrEqual(10240, $limit);
    }
}
