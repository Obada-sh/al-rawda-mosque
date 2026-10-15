<?php

namespace App\Services\Backup;

/**
 * Decides whether a file can be restored, without touching the database:
 * it must be a backup of this system, whole and unaltered, and not taken
 * by a newer version than the one installed.
 */
final class BackupInspector
{
    public function __construct(private readonly DatabaseRestorer $restorer) {}

    /**
     * @return BackupArchive the file, opened and checked
     *
     * @throws InvalidBackup
     */
    public function inspect(string $path): BackupArchive
    {
        $archive = BackupArchive::open($path);

        $archive->verify();
        $this->restorer->inspect($archive->database());

        $unknown = array_diff($archive->manifest->migrations, $this->knownMigrations());

        if ($unknown !== []) {
            throw InvalidBackup::newerVersion('its database has changes this version does not know: '.implode(', ', $unknown));
        }

        return $archive;
    }

    /**
     * The changes of the database this version of the system knows.
     * After a restore, those the backup had not run yet are run.
     *
     * @return list<string>
     */
    private function knownMigrations(): array
    {
        return array_map(
            fn (string $file): string => basename($file, '.php'),
            glob(database_path('migrations/*.php')) ?: [],
        );
    }
}
