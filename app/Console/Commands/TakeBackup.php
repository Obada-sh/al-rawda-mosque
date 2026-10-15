<?php

namespace App\Console\Commands;

use App\Actions\Backups\CreateBackup;
use App\Actions\Backups\RunDailyBackup;
use App\Exceptions\BusinessRuleViolation;
use Illuminate\Console\Command;

/**
 * Takes a backup from the command line.
 */
class TakeBackup extends Command
{
    protected $signature = 'rawda:backup {--daily : Take the automatic backup of the day, unless today already has one}';

    protected $description = 'Back up the database and the uploaded files into the backups folder';

    public function handle(CreateBackup $createBackup, RunDailyBackup $runDailyBackup): int
    {
        try {
            $backup = $this->option('daily') ? $runDailyBackup->handle() : $createBackup->handle();
        } catch (BusinessRuleViolation $violation) {
            $this->error($violation->getMessage());

            return self::FAILURE;
        }

        if ($backup === null) {
            $this->info('Today already has its automatic backup.');

            return self::SUCCESS;
        }

        $this->info("Backup written: {$backup->path}");

        return self::SUCCESS;
    }
}
