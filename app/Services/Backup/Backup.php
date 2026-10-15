<?php

namespace App\Services\Backup;

use App\Enums\BackupKind;
use Carbon\CarbonImmutable;

/**
 * A backup file in the backups folder.
 */
final readonly class Backup
{
    /**
     * @param  string  $name  the file name; it tells when the backup was taken and why
     * @param  string  $path  where the file is
     * @param  CarbonImmutable  $takenAt  when the data was copied
     */
    public function __construct(
        public string $name,
        public string $path,
        public BackupKind $kind,
        public CarbonImmutable $takenAt,
        public int $bytes,
    ) {}
}
