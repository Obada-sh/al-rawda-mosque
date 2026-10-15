<?php

namespace App\Actions\Backups;

use App\Enums\BackupKind;
use App\Exceptions\BusinessRuleViolation;
use App\Services\Backup\Backup;
use App\Services\Backup\BackupArchive;
use App\Services\Backup\BackupConnection;
use App\Services\Backup\BackupManifest;
use App\Services\Backup\BackupStore;
use App\Services\Backup\DatabaseDumper;
use App\Services\Backup\KeptFiles;
use Carbon\CarbonImmutable;
use RuntimeException;

/**
 * Takes a backup: the whole database and the files kept with it, in one
 * compressed file of the backups folder.
 *
 * The file is built aside and moved into the folder when it is complete,
 * so a file found there is always a whole backup.
 */
final class CreateBackup
{
    public function __construct(
        private readonly BackupStore $store,
        private readonly BackupConnection $connections,
        private readonly DatabaseDumper $dumper,
        private readonly KeptFiles $files,
    ) {}

    /**
     * @throws BusinessRuleViolation backup.folder_not_writable
     */
    public function handle(BackupKind $kind = BackupKind::Manual): Backup
    {
        if (! $this->store->isWritable()) {
            throw BackupViolation::folderNotWritable($this->store);
        }

        $takenAt = CarbonImmutable::now()->startOfSecond();
        $database = self::temporaryFile();
        $archive = self::temporaryFile();

        try {
            [$tables, $migrations] = $this->dumpDatabase($database);

            BackupArchive::write(
                $archive,
                fn (string $databaseSha256, array $files): BackupManifest => new BackupManifest(
                    takenAt: $takenAt,
                    kind: $kind,
                    databaseSha256: $databaseSha256,
                    tables: $tables,
                    migrations: $migrations,
                    files: $files,
                ),
                $database,
                $this->files->all(),
            );

            $path = $this->store->pathFor($kind, $takenAt);

            if (! @rename($archive, $path) && ! @copy($archive, $path)) {
                throw new RuntimeException("The backup could not be placed in [{$this->store->directory()}].");
            }
        } finally {
            @unlink($database);
            @unlink($archive);
        }

        return $this->store->find(basename($path))
            ?? throw new RuntimeException('The backup that was just written cannot be found.');
    }

    /**
     * @return array{array<string, int>, list<string>} the rows of each table, and the migrations the database has run
     */
    private function dumpDatabase(string $file): array
    {
        $output = fopen($file, 'wb');

        if ($output === false) {
            throw new RuntimeException('A temporary file for the backup cannot be opened.');
        }

        $connection = $this->connections->open();

        try {
            $tables = $this->dumper->dump($connection, $output);

            $migrations = $connection->getSchemaBuilder()->hasTable('migrations')
                ? $connection->table('migrations')->orderBy('id')->pluck('migration')->map(fn (mixed $name): string => (string) $name)->all()
                : [];
        } finally {
            fclose($output);
            $this->connections->close();
        }

        return [$tables, $migrations];
    }

    private static function temporaryFile(): string
    {
        $file = tempnam(sys_get_temp_dir(), 'rwd');

        if ($file === false) {
            throw new RuntimeException('A temporary file for the backup cannot be created.');
        }

        return $file;
    }
}
