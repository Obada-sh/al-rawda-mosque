<?php

namespace Tests\Unit;

use App\Services\Backup\DriveCopySettings;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

/**
 * When the evening copy for Google Drive is due, and which copies are kept.
 */
class DriveCopySettingsTest extends TestCase
{
    public function test_the_copy_is_due_from_its_time_each_day(): void
    {
        $settings = new DriveCopySettings(folder: 'G:\My Drive\نسخ', time: '20:00');

        // Before the time: the copy that was due last is yesterday's.
        $this->assertSame('2026-10-06 20:00:00', $settings->lastDueAt(CarbonImmutable::parse('2026-10-07 19:59:59'))->format('Y-m-d H:i:s'));
        $this->assertSame('2026-10-07 20:00:00', $settings->lastDueAt(CarbonImmutable::parse('2026-10-07 20:00:00'))->format('Y-m-d H:i:s'));
        $this->assertSame('2026-10-07 20:00:00', $settings->lastDueAt(CarbonImmutable::parse('2026-10-08 06:30:00'))->format('Y-m-d H:i:s'));

        $early = new DriveCopySettings(folder: 'G:\My Drive\نسخ', time: '00:15');
        $this->assertSame('2026-10-07 00:15:00', $early->lastDueAt(CarbonImmutable::parse('2026-10-07 23:59:00'))->format('Y-m-d H:i:s'));
    }

    public function test_the_copies_of_the_last_days_are_kept_today_included(): void
    {
        $now = CarbonImmutable::parse('2026-10-07 20:05:00');

        $this->assertSame('2026-10-01 00:00:00', (new DriveCopySettings(keepDays: 7))->keptFrom($now)->format('Y-m-d H:i:s'));
        $this->assertSame('2026-10-07 00:00:00', (new DriveCopySettings(keepDays: 1))->keptFrom($now)->format('Y-m-d H:i:s'));
    }

    public function test_without_a_folder_there_is_no_evening_copy(): void
    {
        $this->assertFalse((new DriveCopySettings)->isOn());
        $this->assertTrue((new DriveCopySettings(folder: 'G:\My Drive'))->isOn());
        $this->assertSame(DriveCopySettings::DEFAULT_TIME, (new DriveCopySettings)->time);
        $this->assertSame(7, (new DriveCopySettings)->keepDays);
    }
}
