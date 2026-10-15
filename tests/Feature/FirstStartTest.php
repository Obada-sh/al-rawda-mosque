<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\TajweedLevel;
use App\Models\User;
use App\Services\Backup\BackupStore;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * The first start of the system on a computer: start.bat finds a
 * database with nothing in it and must leave a system that is ready to
 * be used. The tables are dropped and built here, so these tests rebuild
 * the testing database instead of running inside a transaction.
 */
class FirstStartTest extends TestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-10-07 17:00:00');

        File::deleteDirectory(config('rawda.backups.path'));
    }

    public function test_a_new_database_gets_its_tables_and_the_lists_the_system_starts_with(): void
    {
        // A new computer: the database is there, and empty.
        $this->artisan('db:wipe', ['--force' => true])->assertSuccessful();
        $this->assertFalse(Schema::hasTable('students'));

        $this->artisan('rawda:prepare', ['--wait' => 0])
            ->expectsOutputToContain('Database: new, filled with the starting lists.')
            ->expectsOutputToContain('The system is ready.')
            ->assertSuccessful();

        $this->assertSame(
            ['المستوى الأول', 'المستوى الثاني', 'المستوى الثالث', 'المستوى الرابع'],
            TajweedLevel::query()->orderBy('sort_order')->pluck('name')->all(),
        );
        $this->assertEqualsCanonicalizing(['مشتريات', 'إطعام', 'لوجستي'], Department::query()->pluck('name')->all());
        $this->assertSame(30, DB::table('quran_juz')->count());

        // No account is ever created for the admin: the person at the computer does that.
        $this->assertSame(0, User::query()->count());
        $this->get('/admin')->assertRedirect('/setup');

        // There was nothing to save before the tables were built; the day has its backup.
        $this->assertSame(['auto'], array_map(fn ($backup): string => $backup->kind->value, app(BackupStore::class)->all()));
    }

    public function test_the_starting_lists_are_given_once(): void
    {
        $this->artisan('db:wipe', ['--force' => true])->assertSuccessful();
        $this->artisan('rawda:prepare', ['--wait' => 0])->assertSuccessful();

        // The mosque names its own departments and levels.
        Department::query()->delete();
        TajweedLevel::query()->where('sort_order', '>', 2)->delete();
        Department::query()->create(['name' => 'صيانة']);

        $this->travelTo('2026-10-08 08:00:00');

        $this->artisan('rawda:prepare', ['--wait' => 0])
            ->doesntExpectOutputToContain('starting lists')
            ->assertSuccessful();

        $this->assertSame(['صيانة'], Department::query()->pluck('name')->all());
        $this->assertSame(2, TajweedLevel::query()->count());
    }
}
