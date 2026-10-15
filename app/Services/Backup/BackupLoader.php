<?php

namespace App\Services\Backup;

use App\Contracts\SettingsStore;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Facades\Artisan;
use LogicException;

/**
 * Puts the system back to what a backup holds: the database, and the
 * files kept with it. The backup must have passed BackupInspector.
 */
final class BackupLoader
{
    public function __construct(
        private readonly DatabaseManager $databases,
        private readonly BackupConnection $connections,
        private readonly DatabaseRestorer $restorer,
        private readonly KeptFiles $files,
    ) {}

    public function load(BackupArchive $archive): void
    {
        // Replacing a table waits for every open transaction that used it: it would wait for ever on our own.
        if ($this->databases->connection()->transactionLevel() > 0) {
            throw new LogicException('A backup cannot be restored inside a database transaction.');
        }

        $connection = $this->connections->open();

        try {
            $this->restorer->restore($connection, $archive->database());
        } finally {
            $this->connections->close();
        }

        $this->files->replaceWith($archive->files());

        // A backup taken by an older version of the system is brought up to date.
        Artisan::call('migrate', ['--force' => true]);

        // What was read from the old data is read again.
        app()->forgetInstance(SettingsStore::class);
    }
}
