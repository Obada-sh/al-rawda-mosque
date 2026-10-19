<?php

namespace App\Actions\Backups;

use App\Contracts\AdminNotifier;
use App\Exceptions\BusinessRuleViolation;
use App\Services\Backup\DriveCopySetup;
use App\Services\Demo\DemoMode;
use App\Support\SettingKey;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Puts the evening copy in the Google Drive folder when it is due, on
 * behalf of a page the admin has open (the system runs on a laptop with
 * no scheduler: the pages ask every few minutes).
 *
 * It is due once a day from the time the admin chose; a laptop that was
 * off that evening puts it there the next time it is used. A copy that
 * cannot be made never stops the admin from working, and never goes
 * unnoticed either: the admin is told, once a day, in the notifications,
 * and it is tried again half an hour later. Old copies that could not be
 * removed from the folder are also told, for the admin to remove them.
 */
final class GuardEveningDriveCopy
{
    private const LOCK = 'rawda:drive-copy';

    public function __construct(
        private readonly DriveCopySetup $setup,
        private readonly CopyBackupToDrive $copyBackupToDrive,
        private readonly AdminNotifier $notifier,
        private readonly DemoMode $demo,
    ) {}

    /**
     * @return bool whether a copy was put in the folder now
     */
    public function handle(): bool
    {
        $now = CarbonImmutable::now();

        if ($this->demo->isOn() || ! $this->setup->isDue($this->setup->settings(), $now) || ! $this->setup->mayTryNow($now)) {
            return false;
        }

        $lock = Cache::lock(self::LOCK, 600);

        if (! $lock->get()) {
            return false;
        }

        try {
            $result = $this->copyBackupToDrive->handle();
        } catch (BusinessRuleViolation $violation) {
            $this->tell($violation->getMessage(), $now);

            return false;
        } catch (Throwable $failure) {
            report($failure);
            $this->tell(CopyBackupToDrive::UNEXPECTED, $now);

            return false;
        } finally {
            $lock->release();
        }

        if ($result->notRemoved !== [] && $this->setup->firstTimeToday(SettingKey::DRIVE_LEFTOVERS_REPORTED_ON, $now)) {
            $this->notifier->driveCopiesNotRemoved($result->notRemoved, $result->folder);
        }

        return true;
    }

    private function tell(string $reason, CarbonImmutable $now): void
    {
        if ($this->setup->firstTimeToday(SettingKey::DRIVE_FAILURE_REPORTED_ON, $now)) {
            $this->notifier->driveCopyFailed($reason);
        }
    }
}
