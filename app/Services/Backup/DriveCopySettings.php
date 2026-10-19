<?php

namespace App\Services\Backup;

use Carbon\CarbonImmutable;

/**
 * The evening copy for Google Drive, as the admin set it up: the folder on
 * this computer that the Google Drive program sends to Drive (none = no
 * evening copy), the address of that folder on drive.google.com, the time
 * from which the copy of the day is due, and how many days of copies are
 * kept.
 */
final readonly class DriveCopySettings
{
    public const DEFAULT_TIME = '20:00';

    public const DEFAULT_KEEP_DAYS = 7;

    public const MAX_KEEP_DAYS = 60;

    /**
     * @param  string  $time  H:i
     */
    public function __construct(
        public ?string $folder = null,
        public ?string $link = null,
        public string $time = self::DEFAULT_TIME,
        public int $keepDays = self::DEFAULT_KEEP_DAYS,
    ) {}

    public function isOn(): bool
    {
        return $this->folder !== null;
    }

    /**
     * When the last copy was due, seen from that moment: today at the time
     * once it has come, otherwise yesterday at the time. A copy put in the
     * folder since then is the copy of that evening.
     */
    public function lastDueAt(CarbonImmutable $now): CarbonImmutable
    {
        [$hour, $minute] = array_map('intval', explode(':', $this->time));

        $today = $now->setTime($hour, $minute);

        return $now->greaterThanOrEqualTo($today) ? $today : $today->subDay();
    }

    /**
     * The first moment whose copies are kept: those of the last days, today included.
     */
    public function keptFrom(CarbonImmutable $now): CarbonImmutable
    {
        return $now->startOfDay()->subDays($this->keepDays - 1);
    }
}
