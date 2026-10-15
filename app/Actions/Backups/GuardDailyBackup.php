<?php

namespace App\Actions\Backups;

use App\Contracts\AdminNotifier;
use App\Contracts\SettingsStore;
use App\Support\Dates;
use App\Support\SettingKey;
use Throwable;

/**
 * Takes the automatic backup of the day on behalf of a page the admin is
 * opening. A backup that cannot be taken must never stop the admin from
 * working, and must never go unnoticed either: the failure is logged and
 * the admin is told, once a day, in the notifications.
 */
final class GuardDailyBackup
{
    public function __construct(
        private readonly RunDailyBackup $runDailyBackup,
        private readonly SettingsStore $settings,
        private readonly AdminNotifier $notifier,
    ) {}

    /**
     * @return bool false when the backup of the day could not be taken
     */
    public function handle(): bool
    {
        try {
            $this->runDailyBackup->handle();

            return true;
        } catch (Throwable $failure) {
            report($failure);
        }

        $today = Dates::today()->toDateString();

        if ($this->settings->get(SettingKey::BACKUP_FAILURE_REPORTED_ON) !== $today) {
            $this->notifier->automaticBackupFailed();
            $this->settings->set(SettingKey::BACKUP_FAILURE_REPORTED_ON, $today);
        }

        return false;
    }
}
