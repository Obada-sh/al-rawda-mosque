<?php

namespace App\Actions\Backups;

use App\Enums\BackupKind;
use App\Exceptions\BusinessRuleViolation;
use App\Services\Backup\Backup;
use App\Services\Backup\BackupStore;
use App\Services\Backup\DriveCopySetup;
use App\Services\Backup\DriveFolder;
use App\Services\Demo\DemoMode;
use Carbon\CarbonImmutable;
use Throwable;

/**
 * Takes a backup now and puts a copy of it in the Google Drive folder,
 * where the Google Drive program sends it to Drive when the internet is
 * there. Then removes the evening copies older than the days kept, from
 * that folder and from the backups folder of this computer.
 *
 * Each attempt is noted (DriveCopySetup), so the backups page tells how
 * the last one went, and a failed one is tried again later.
 */
final class CopyBackupToDrive
{
    /** What is noted when something else than a broken rule stopped the copy. */
    public const UNEXPECTED = 'حدث خطأ غير متوقع أثناء أخذ النسخة أو وضعها في المجلد (التفاصيل في سجل النظام).';

    public function __construct(
        private readonly DriveCopySetup $setup,
        private readonly CreateBackup $createBackup,
        private readonly BackupStore $store,
        private readonly DemoMode $demo,
    ) {}

    /**
     * @throws BusinessRuleViolation drive.demo, drive.off, drive.folder_missing, drive.not_copied, backup.folder_not_writable
     */
    public function handle(): DriveCopyResult
    {
        if ($this->demo->isOn()) {
            throw DriveCopyViolation::demo();
        }

        $settings = $this->setup->settings();

        if (! $settings->isOn()) {
            throw DriveCopyViolation::off();
        }

        $now = CarbonImmutable::now();
        $folder = new DriveFolder((string) $settings->folder);

        try {
            $backup = $this->copyInto($folder);
        } catch (BusinessRuleViolation $violation) {
            $this->setup->failed($violation->getMessage(), $now);

            throw $violation;
        } catch (Throwable $failure) {
            $this->setup->failed(self::UNEXPECTED, $now);

            throw $failure;
        }

        $this->setup->copied($backup->name, $now);

        $keptFrom = $settings->keptFrom($now);
        $leftOver = $folder->removeTakenBefore($keptFrom);
        $this->removeOwnTakenBefore($keptFrom);

        return new DriveCopyResult($backup, $folder->path, $leftOver['removed'], $leftOver['notRemoved']);
    }

    private function copyInto(DriveFolder $folder): Backup
    {
        // Checked first: no backup is taken for a folder that is not there.
        if (! $folder->exists()) {
            throw DriveCopyViolation::folderMissing($folder->path);
        }

        $backup = $this->createBackup->handle(BackupKind::Evening);

        if (! $folder->put($backup)) {
            throw DriveCopyViolation::notCopied($folder->path);
        }

        return $backup;
    }

    /**
     * The evening backups of this computer follow the copies on Drive.
     */
    private function removeOwnTakenBefore(CarbonImmutable $moment): void
    {
        foreach ($this->store->all() as $backup) {
            if ($backup->kind === BackupKind::Evening && $backup->takenAt->lessThan($moment)) {
                $this->store->delete($backup);
            }
        }
    }
}
