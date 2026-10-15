<?php

namespace App\Console\Commands;

use App\Actions\Backups\CreateBackup;
use App\Actions\Backups\RunDailyBackup;
use App\Enums\BackupKind;
use Illuminate\Console\Command;
use Illuminate\Database\Migrations\Migrator;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Gets the system ready to be used. start.bat runs it before it starts
 * the server:
 *
 *  1. waits for MySQL to answer (it may still be starting with Windows);
 *  2. brings the database up to date; when an update changes a database
 *     that already holds data, a backup is taken first; a database that
 *     is new gets the lists the system starts with (the tajweed levels
 *     and the departments), once;
 *  3. takes the automatic backup of the day.
 *
 * A backup that cannot be taken is reported but does not stop the system
 * from starting: the mosque must be able to work.
 */
class PrepareSystem extends Command
{
    protected $signature = 'rawda:prepare {--wait=40 : Seconds to wait for the database to answer}';

    protected $description = 'Get the system ready: wait for MySQL, update the database, take the daily backup';

    public function handle(Migrator $migrator, CreateBackup $createBackup, RunDailyBackup $runDailyBackup): int
    {
        if (! is_file(base_path('.env')) || blank(config('app.key'))) {
            $this->error('The settings file (.env) is missing or has no APP_KEY. Copy the .env file of the installation into this folder (see README).');

            return self::FAILURE;
        }

        if (! $this->databaseAnswers((int) $this->option('wait'))) {
            $this->error('The database does not answer. Check that the MySQL service is running, and the DB_ lines of the .env file.');

            return self::FAILURE;
        }

        $this->line('Database: connected.');

        // Settings cached by an earlier version must not outlive an update.
        $this->callSilent('config:clear');
        $this->callSilent('route:clear');

        $ran = $migrator->repositoryExists() ? $migrator->getRepository()->getRan() : [];
        $pending = array_diff(array_keys($migrator->getMigrationFiles(database_path('migrations'))), $ran);

        $isNew = $ran === [];

        if ($pending !== []) {
            if (! $isNew) {
                $this->attempt('Backup before the update', fn () => $createBackup->handle(BackupKind::BeforeUpdate)->name);
            }

            if ($this->call('migrate', ['--force' => true]) !== self::SUCCESS) {
                $this->error('The database could not be updated. The backup taken just before the update is in the backups folder.');

                return self::FAILURE;
            }

            // Only a new database: what the admin removes from these lists later stays removed.
            if ($isNew) {
                $this->callSilent('db:seed', ['--force' => true]);
                $this->line('Database: new, filled with the starting lists.');
            }
        }

        $this->line('Database: up to date.');

        $this->attempt('Daily backup', fn () => $runDailyBackup->handle()?->name ?? 'already taken today');

        $this->info('The system is ready.');

        return self::SUCCESS;
    }

    private function databaseAnswers(int $seconds): bool
    {
        $deadline = time() + max(0, $seconds);
        $created = false;

        while (true) {
            try {
                DB::connection()->getPdo();

                return true;
            } catch (Throwable $failure) {
                DB::purge();

                // MySQL answers, but the database of the system is not there yet: a first start.
                if (! $created && str_contains($failure->getMessage(), '[1049]') && ($created = $this->createDatabase())) {
                    continue;
                }

                if (time() >= $deadline) {
                    return false;
                }

                $this->line('Waiting for MySQL...');
                sleep(2);
            }
        }
    }

    /**
     * Creates the empty database named in the .env file. Its tables are
     * created by the update that follows.
     */
    private function createDatabase(): bool
    {
        $settings = config('database.connections.'.config('database.default'));
        $name = (string) ($settings['database'] ?? '');

        if (preg_match('/^[A-Za-z0-9_]+$/', $name) !== 1) {
            return false;
        }

        try {
            config(['database.connections.rawda_server' => ['database' => null] + $settings]);

            DB::connection('rawda_server')->statement(
                "CREATE DATABASE IF NOT EXISTS `{$name}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci",
            );

            $this->line("Database [{$name}]: created.");

            return true;
        } catch (Throwable) {
            return false;
        } finally {
            DB::purge('rawda_server');
        }
    }

    /**
     * Runs a step that may fail without stopping the start.
     *
     * @param  callable(): string  $step  returns what to print next to the label
     */
    private function attempt(string $label, callable $step): void
    {
        try {
            $this->line("{$label}: {$step()}.");
        } catch (Throwable $failure) {
            report($failure);

            $this->warn("{$label}: FAILED - {$failure->getMessage()}");
        }
    }
}
