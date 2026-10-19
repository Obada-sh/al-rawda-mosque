<?php

namespace App\Actions\Backups;

use App\Services\Backup\Backup;

/**
 * An evening copy put in the Google Drive folder, and the older copies
 * that were removed from it, or could not be.
 */
final readonly class DriveCopyResult
{
    /**
     * @param  Backup  $backup  the backup of this computer the copy is of
     * @param  string  $folder  the Google Drive folder
     * @param  list<string>  $removed  names of the older copies removed from the folder
     * @param  list<string>  $notRemoved  names of the older copies still in it
     */
    public function __construct(
        public Backup $backup,
        public string $folder,
        public array $removed,
        public array $notRemoved,
    ) {}
}
