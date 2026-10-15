<?php

namespace App\Actions\Backups;

use App\Enums\BackupKind;
use App\Exceptions\BusinessRuleViolation;
use App\Services\Backup\Backup;
use App\Services\Backup\BackupStore;
use App\Support\Dates;
use Illuminate\Contracts\Config\Repository as Config;

/**
 * Takes the automatic backup of the day, unless the day already has one,
 * and keeps only the newest of the automatic backups.
 *
 * The system runs on a laptop that is not always on, so this is tied to
 * the first use of each day rather than to a clock.
 */
final class RunDailyBackup
{
    public function __construct(
        private readonly BackupStore $store,
        private readonly CreateBackup $createBackup,
        private readonly Config $config,
    ) {}

    /**
     * @return Backup|null the backup taken now; null when today already has its automatic backup
     *
     * @throws BusinessRuleViolation backup.folder_not_writable
     */
    public function handle(): ?Backup
    {
        if ($this->store->takenOn(Dates::today()->toDateString(), BackupKind::Automatic) !== null) {
            return null;
        }

        $backup = $this->createBackup->handle(BackupKind::Automatic);

        $this->dropOldest();

        return $backup;
    }

    /**
     * Backups the admin took by hand, or that guard a restore, are never dropped here.
     */
    private function dropOldest(): void
    {
        $keep = max(1, (int) $this->config->get('rawda.backups.keep', 30));

        $automatic = array_values(array_filter(
            $this->store->all(),
            fn (Backup $backup): bool => $backup->kind === BackupKind::Automatic,
        ));

        foreach (array_slice($automatic, $keep) as $old) {
            $this->store->delete($old);
        }
    }
}
