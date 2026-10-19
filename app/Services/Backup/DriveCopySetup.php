<?php

namespace App\Services\Backup;

use App\Contracts\SettingsStore;
use App\Support\SettingKey;
use Carbon\CarbonImmutable;
use Throwable;

/**
 * Keeps the set-up of the evening copy for Google Drive, and how its last
 * attempts went, in the settings; and says when a copy is due. The only
 * reader and writer of the DRIVE_* settings.
 */
final class DriveCopySetup
{
    /** After a failed attempt, the next one waits this long. */
    public const RETRY_AFTER_MINUTES = 30;

    private const MOMENT = 'Y-m-d H:i:s';

    public function __construct(private readonly SettingsStore $settings) {}

    public function settings(): DriveCopySettings
    {
        $time = (string) $this->settings->get(SettingKey::DRIVE_TIME, DriveCopySettings::DEFAULT_TIME);
        $days = (int) $this->settings->get(SettingKey::DRIVE_KEEP_DAYS, (string) DriveCopySettings::DEFAULT_KEEP_DAYS);

        return new DriveCopySettings(
            folder: self::filledOrNull($this->settings->get(SettingKey::DRIVE_FOLDER)),
            link: self::filledOrNull($this->settings->get(SettingKey::DRIVE_LINK)),
            time: preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $time) === 1 ? $time : DriveCopySettings::DEFAULT_TIME,
            keepDays: $days >= 1 && $days <= DriveCopySettings::MAX_KEEP_DAYS ? $days : DriveCopySettings::DEFAULT_KEEP_DAYS,
        );
    }

    /**
     * A new set-up starts with a clean record of failures: the next
     * attempt is not held back by one that failed with the old set-up.
     */
    public function save(DriveCopySettings $settings): void
    {
        $this->settings->set(SettingKey::DRIVE_FOLDER, $settings->folder);
        $this->settings->set(SettingKey::DRIVE_LINK, $settings->link);
        $this->settings->set(SettingKey::DRIVE_TIME, $settings->time);
        $this->settings->set(SettingKey::DRIVE_KEEP_DAYS, (string) $settings->keepDays);
        $this->clearFailure();
    }

    public function state(): DriveCopyState
    {
        return new DriveCopyState(
            copiedAt: self::moment($this->settings->get(SettingKey::DRIVE_COPIED_AT)),
            copiedName: self::filledOrNull($this->settings->get(SettingKey::DRIVE_COPIED_NAME)),
            failedAt: self::moment($this->settings->get(SettingKey::DRIVE_FAILED_AT)),
            failure: self::filledOrNull($this->settings->get(SettingKey::DRIVE_FAILURE)),
        );
    }

    public function copied(string $name, CarbonImmutable $at): void
    {
        $this->settings->set(SettingKey::DRIVE_COPIED_AT, $at->format(self::MOMENT));
        $this->settings->set(SettingKey::DRIVE_COPIED_NAME, $name);
        $this->clearFailure();
    }

    public function failed(string $reason, CarbonImmutable $at): void
    {
        $this->settings->set(SettingKey::DRIVE_FAILED_AT, $at->format(self::MOMENT));
        $this->settings->set(SettingKey::DRIVE_FAILURE, $reason);
    }

    /**
     * Whether the copy of the evening is still to be put in the folder:
     * none was put there since it was last due (see lastDueAt). A computer
     * that was off that evening puts it there the next time it is used.
     */
    public function isDue(DriveCopySettings $settings, CarbonImmutable $now): bool
    {
        $copiedAt = $this->state()->copiedAt;

        return $settings->isOn() && ($copiedAt === null || $copiedAt->lessThan($settings->lastDueAt($now)));
    }

    /**
     * Whether an attempt may be made now: none failed in the last minutes.
     */
    public function mayTryNow(CarbonImmutable $now): bool
    {
        $failedAt = $this->state()->failedAt;

        return $failedAt === null || $failedAt->addMinutes(self::RETRY_AFTER_MINUTES)->lessThanOrEqualTo($now);
    }

    /**
     * Whether the admin was told about that today already; if not, it is
     * noted that they are told now.
     *
     * @param  string  $key  SettingKey::DRIVE_FAILURE_REPORTED_ON or SettingKey::DRIVE_LEFTOVERS_REPORTED_ON
     */
    public function firstTimeToday(string $key, CarbonImmutable $now): bool
    {
        if ($this->settings->get($key) === $now->toDateString()) {
            return false;
        }

        $this->settings->set($key, $now->toDateString());

        return true;
    }

    private function clearFailure(): void
    {
        $this->settings->set(SettingKey::DRIVE_FAILED_AT, null);
        $this->settings->set(SettingKey::DRIVE_FAILURE, null);
    }

    private static function moment(?string $value): ?CarbonImmutable
    {
        if (blank($value)) {
            return null;
        }

        try {
            return CarbonImmutable::createFromFormat(self::MOMENT, $value) ?: null;
        } catch (Throwable) {
            return null;
        }
    }

    private static function filledOrNull(?string $value): ?string
    {
        return filled($value) ? $value : null;
    }
}
