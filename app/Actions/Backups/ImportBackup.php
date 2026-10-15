<?php

namespace App\Actions\Backups;

use App\Enums\BackupKind;
use App\Exceptions\BusinessRuleViolation;
use App\Http\Requests\Backups\BackupUploadRequest;
use App\Services\Backup\Backup;
use App\Services\Backup\BackupInspector;
use App\Services\Backup\BackupStore;
use App\Services\Backup\InvalidBackup;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Adds a backup file brought from outside (a USB stick) to the backups
 * folder, after checking it from end to end. Nothing is restored yet:
 * the admin restores it from the list like any other backup.
 */
final class ImportBackup
{
    public function __construct(
        private readonly BackupStore $store,
        private readonly BackupInspector $inspector,
    ) {}

    /**
     * @param  array<string, mixed>  $input  'backup' => the uploaded .zip file
     *
     * @throws ValidationException
     * @throws BusinessRuleViolation backup.not_a_backup | backup.damaged | backup.newer_version | backup.folder_not_writable
     */
    public function handle(array $input): Backup
    {
        /** @var UploadedFile $file */
        $file = BackupUploadRequest::validateData($input)['backup'];

        if (! $this->store->isWritable()) {
            throw BackupViolation::folderNotWritable($this->store);
        }

        try {
            $takenAt = $this->inspector->inspect($file->getRealPath())->manifest->takenAt;
        } catch (InvalidBackup $exception) {
            throw BackupViolation::from($exception);
        }

        // The file is listed at the moment its data was copied, in the time of this machine.
        $name = BackupStore::nameOf(
            BackupKind::Uploaded,
            CarbonImmutable::parse($takenAt)->setTimezone(date_default_timezone_get()),
        );

        if (! @copy($file->getRealPath(), $this->store->directory().DIRECTORY_SEPARATOR.$name)) {
            throw new RuntimeException("The uploaded backup could not be placed in [{$this->store->directory()}].");
        }

        return $this->store->find($name)
            ?? throw new RuntimeException('The backup that was just added cannot be found.');
    }
}
