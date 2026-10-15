<?php

namespace App\Actions\Backups;

use App\Enums\BackupKind;
use App\Exceptions\BusinessRuleViolation;
use App\Http\Requests\Backups\BackupNameRequest;
use App\Services\Backup\Backup;
use App\Services\Backup\BackupArchive;
use App\Services\Backup\BackupInspector;
use App\Services\Backup\BackupLoader;
use App\Services\Backup\BackupStore;
use App\Services\Backup\InvalidBackup;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Puts the system back to what one of its backups holds.
 *
 * Everything recorded after that backup was taken is replaced, so the
 * action is careful twice: the file is checked from end to end before
 * anything is touched, and a backup of the present state is taken first.
 * If the restore fails half-way, that backup is put back.
 */
final class RestoreBackup
{
    public function __construct(
        private readonly BackupStore $store,
        private readonly BackupInspector $inspector,
        private readonly BackupLoader $loader,
        private readonly CreateBackup $createBackup,
    ) {}

    /**
     * @param  array<string, mixed>  $input  'name' => the file name of the backup
     * @return Backup the backup of the state that was replaced, to undo the restore with
     *
     * @throws ValidationException
     * @throws BusinessRuleViolation backup.not_found | backup.not_a_backup | backup.damaged | backup.newer_version | backup.folder_not_writable | backup.restore_failed
     */
    public function handle(array $input): Backup
    {
        $name = (string) BackupNameRequest::validateData($input)['name'];
        $backup = $this->store->find($name) ?? throw BackupViolation::notFound();

        try {
            $archive = $this->inspector->inspect($backup->path);
        } catch (InvalidBackup $exception) {
            throw BackupViolation::from($exception);
        }

        $before = $this->createBackup->handle(BackupKind::BeforeRestore);

        try {
            $this->loader->load($archive);
        } catch (Throwable $failure) {
            report($failure);

            // Put back what was there a moment ago.
            $this->loader->load(BackupArchive::open($before->path));

            throw BackupViolation::restoreFailed();
        }

        return $before;
    }
}
