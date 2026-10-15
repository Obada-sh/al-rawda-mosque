<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Creates the MySQL database the test suite runs on (see phpunit.xml).
 */
class PrepareTestingDatabase extends Command
{
    protected $signature = 'rawda:prepare-testing-db {name=rawda_testing : Name of the testing database}';

    protected $description = 'Create the MySQL database used by the automated tests, if it does not exist';

    public function handle(): int
    {
        $name = (string) $this->argument('name');

        if (preg_match('/^[A-Za-z0-9_]+$/', $name) !== 1) {
            $this->error('The database name may contain letters, digits and underscores only.');

            return self::FAILURE;
        }

        try {
            DB::statement("CREATE DATABASE IF NOT EXISTS `{$name}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        } catch (Throwable $exception) {
            $this->error('Could not create the testing database: '.$exception->getMessage());

            return self::FAILURE;
        }

        $this->info("Testing database [{$name}] is ready.");

        return self::SUCCESS;
    }
}
