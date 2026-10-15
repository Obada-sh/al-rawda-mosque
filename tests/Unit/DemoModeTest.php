<?php

namespace Tests\Unit;

use App\Services\Demo\DemoMode;
use PHPUnit\Framework\TestCase;

class DemoModeTest extends TestCase
{
    private const DEMO_PORT = 8010;

    public function test_the_database_alone_decides_whether_the_system_is_the_demo(): void
    {
        $this->assertTrue((new DemoMode('rawda_demo', self::DEMO_PORT))->isOn());
        $this->assertTrue((new DemoMode('mosque_2026_demo', self::DEMO_PORT))->isOn());

        $this->assertFalse((new DemoMode('rawda', self::DEMO_PORT))->isOn());
        $this->assertFalse((new DemoMode('rawda_testing', self::DEMO_PORT))->isOn());
        // The ending counts, not the word.
        $this->assertFalse((new DemoMode('rawda_demo_copy', self::DEMO_PORT))->isOn());
        $this->assertFalse((new DemoMode('demo', self::DEMO_PORT))->isOn());
        $this->assertFalse((new DemoMode('_demo', self::DEMO_PORT))->isOn());
        $this->assertFalse((new DemoMode('', self::DEMO_PORT))->isOn());
    }

    public function test_the_real_data_would_show_at_the_address_of_the_demo_only(): void
    {
        $real = new DemoMode('rawda', self::DEMO_PORT);

        $this->assertTrue($real->wouldShowRealDataAt(self::DEMO_PORT));
        $this->assertFalse($real->wouldShowRealDataAt(8000));
        $this->assertFalse($real->wouldShowRealDataAt(80));
        $this->assertFalse($real->wouldShowRealDataAt(0));
    }

    public function test_the_demo_shows_no_real_data_wherever_it_is_served(): void
    {
        $demo = new DemoMode('rawda_demo', self::DEMO_PORT);

        $this->assertFalse($demo->wouldShowRealDataAt(self::DEMO_PORT));
        $this->assertFalse($demo->wouldShowRealDataAt(8000));
        $this->assertSame('rawda_demo', $demo->database());
    }
}
