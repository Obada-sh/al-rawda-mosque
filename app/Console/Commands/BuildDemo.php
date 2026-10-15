<?php

namespace App\Console\Commands;

use App\Models\CourseSession;
use App\Models\User;
use App\Services\Demo\DemoMode;
use App\Support\Dates;
use Database\Seeders\DemoSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

/**
 * Fills the demo database with invented data (see DemoSeeder).
 *
 * Building the demo empties the database first. The command therefore
 * asks whether this system is the demo before anything else, and only
 * goes on when it is: the real data can never be its target. demo.bat
 * starts the system on a demo database.
 */
class BuildDemo extends Command
{
    /** A demo nobody attended a class of for longer than this has nothing to show for "this week". */
    public const STALE_AFTER_DAYS = 7;

    protected $signature = 'rawda:demo {--fresh : Build the demo again from nothing, even when it is already there}';

    protected $description = 'Fill the demo database with invented data (refuses any database whose name does not end with _demo)';

    public function handle(DemoMode $demo): int
    {
        if (! $demo->isOn()) {
            $this->error("The demo is invented data. It refuses to touch the database [{$demo->database()}]: the name of a demo database ends with \"".DemoMode::DATABASE_SUFFIX.'". Use demo.bat.');

            return self::FAILURE;
        }

        if (! $this->option('fresh') && $this->isBuilt() && ! $this->shouldBeBuiltAgain()) {
            $this->info('The demo is already there (use --fresh to build it again).');

            return self::SUCCESS;
        }

        $this->line('Building the demo: this takes about a minute...');

        $this->call('migrate:fresh', ['--force' => true]);
        $this->call('db:seed', ['--class' => DemoSeeder::class, '--force' => true]);

        $this->info('The demo is ready. Its sign-in page shows the account to get in with.');

        return self::SUCCESS;
    }

    /**
     * What the person starting a demo that was left alone is asked.
     */
    public static function staleQuestion(int $days): string
    {
        return "Nobody attended a class of the demo for {$days} days: its pages about today and this week are empty. "
            .'Build it again around today? Everything that was tried in it is lost.';
    }

    private function isBuilt(): bool
    {
        return Schema::hasTable('users') && User::query()->exists();
    }

    /**
     * The demo is built around the day it is built on, and goes stale
     * when nothing is recorded in it afterwards. It is never replaced
     * without a yes: someone may have prepared something in it.
     */
    private function shouldBeBuiltAgain(): bool
    {
        $lastClass = CourseSession::query()->has('attendances')->max('session_date');

        if ($lastClass === null) {
            return false;
        }

        $days = (int) round(Dates::parse($lastClass)->diffInDays(Dates::today()));

        return $days > self::STALE_AFTER_DAYS && $this->confirm(self::staleQuestion($days), false);
    }
}
