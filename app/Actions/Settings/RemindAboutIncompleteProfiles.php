<?php

namespace App\Actions\Settings;

use App\Contracts\AdminNotifier;
use App\Contracts\SettingsStore;
use App\Models\Student;
use App\Support\Dates;
use App\Support\SettingKey;
use Carbon\CarbonInterface;

/**
 * Reminds the admin, every few days, that some student profiles are still
 * incomplete. Runs when the panel is opened, so no scheduler is needed.
 */
final class RemindAboutIncompleteProfiles
{
    public function __construct(
        private readonly SettingsStore $settings,
        private readonly AdminNotifier $notifier,
    ) {}

    /**
     * @return bool whether a reminder was sent
     */
    public function handle(?CarbonInterface $today = null): bool
    {
        $today = Dates::parse($today ?? Dates::today());

        if (! $this->isDue($today)) {
            return false;
        }

        $count = Student::query()->active()->incompleteProfile()->count();

        if ($count === 0) {
            return false;
        }

        $this->notifier->incompleteProfiles($count);
        $this->settings->set(SettingKey::INCOMPLETE_PROFILES_LAST_REMINDED_ON, $today->toDateString());

        return true;
    }

    private function isDue(CarbonInterface $today): bool
    {
        $lastRemindedOn = $this->settings->get(SettingKey::INCOMPLETE_PROFILES_LAST_REMINDED_ON);

        if ($lastRemindedOn === null) {
            return true;
        }

        $intervalDays = max(1, (int) $this->settings->get(
            SettingKey::INCOMPLETE_PROFILES_REMINDER_DAYS,
            (string) SettingKey::DEFAULT_REMINDER_DAYS,
        ));

        return Dates::parse($lastRemindedOn)->diffInDays($today) >= $intervalDays;
    }
}
