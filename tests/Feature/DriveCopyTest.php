<?php

namespace Tests\Feature;

use App\Actions\Backups\CopyBackupToDrive;
use App\Actions\Backups\DriveCopyViolation;
use App\Actions\Backups\GuardEveningDriveCopy;
use App\Actions\Backups\SaveDriveCopySettings;
use App\Enums\BackupKind;
use App\Exceptions\BusinessRuleViolation;
use App\Services\Backup\Backup;
use App\Services\Backup\BackupStore;
use App\Services\Backup\DriveCopySettings;
use App\Services\Backup\DriveCopySetup;
use App\Services\Backup\DriveFolder;
use App\Services\Demo\DemoMode;
use Closure;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * The evening copy for Google Drive: a backup put every evening in the
 * folder the Google Drive program sends to Drive, the old ones removed.
 * The "Drive folder" of these tests is a folder of the system's temporary
 * files, outside the project.
 */
class DriveCopyTest extends TestCase
{
    use RefreshDatabase;

    private string $drive;

    protected function setUp(): void
    {
        parent::setUp();

        // A Wednesday, before the evening.
        $this->travelTo('2026-10-07 17:00:00');

        File::deleteDirectory(config('rawda.backups.path'));

        $this->drive = sys_get_temp_dir().DIRECTORY_SEPARATOR.'rawda-drive-'.Str::lower(Str::random(8));
        File::ensureDirectoryExists($this->drive);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->drive.DIRECTORY_SEPARATOR.'*') ?: [] as $file) {
            @chmod($file, 0666);
        }

        File::deleteDirectory($this->drive);

        parent::tearDown();
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function setUpDrive(array $input = []): DriveCopySettings
    {
        return app(SaveDriveCopySettings::class)->handle($input + [
            'folder' => $this->drive,
            'link' => null,
            'time' => '20:00',
            'keep_days' => 7,
        ]);
    }

    /**
     * @return list<string> the names in the Drive folder
     */
    private function driveFiles(): array
    {
        $names = array_values(array_diff(scandir($this->drive) ?: [], ['.', '..']));
        sort($names);

        return $names;
    }

    private function driveSetup(): DriveCopySetup
    {
        return app(DriveCopySetup::class);
    }

    private function assertBroken(string $rule, Closure $callback): void
    {
        try {
            $callback();
            $this->fail("The rule [{$rule}] was not applied.");
        } catch (BusinessRuleViolation $violation) {
            $this->assertSame($rule, $violation->rule);
        }
    }

    private function place(string $folder, string $name): string
    {
        file_put_contents($path = $folder.DIRECTORY_SEPARATOR.$name, 'not a real backup');

        return $path;
    }

    public function test_the_folder_must_be_one_of_this_computer_outside_the_system(): void
    {
        $save = fn (array $input): DriveCopySettings => $this->setUpDrive($input);

        $this->assertBroken(DriveCopyViolation::FOLDER_MISSING, fn () => $save(['folder' => $this->drive.DIRECTORY_SEPARATOR.'لا يوجد']));
        $this->assertBroken(DriveCopyViolation::NOT_A_FULL_PATH, fn () => $save(['folder' => 'نسخ الروضة']));
        $this->assertBroken(DriveCopyViolation::INSIDE_SYSTEM, fn () => $save(['folder' => storage_path('framework')]));
        $this->assertBroken(DriveCopyViolation::INSIDE_SYSTEM, fn () => $save(['folder' => base_path()]));

        // The backups folder, even outside the system.
        $backups = $this->drive.DIRECTORY_SEPARATOR.'backups';
        File::ensureDirectoryExists($backups);
        config()->set('rawda.backups.path', $backups);
        $this->assertBroken(DriveCopyViolation::INSIDE_SYSTEM, fn () => $save(['folder' => $backups]));

        $this->assertFalse($this->driveSetup()->settings()->isOn());
    }

    public function test_the_set_up_is_checked_and_kept(): void
    {
        foreach ([
            [['time' => '25:00'], ['time']],
            [['time' => null], ['time']],
            [['keep_days' => 0], ['keep_days']],
            [['keep_days' => 61], ['keep_days']],
            [['link' => 'https://example.com/folders/1'], ['link']],
            [['link' => 'drive.google.com/drive/folders/1'], ['link']],
        ] as [$input, $fields]) {
            try {
                $this->setUpDrive($input);
                $this->fail('Accepted: '.json_encode($input));
            } catch (ValidationException $exception) {
                $this->assertSame($fields, array_keys($exception->errors()));
            }
        }

        // As Windows copies a path ("Copy as path"): between quotation marks; Arabic digits in the time.
        $settings = $this->setUpDrive([
            'folder' => ' "'.$this->drive.DIRECTORY_SEPARATOR.'" ',
            'link' => 'https://drive.google.com/drive/folders/abc123',
            'time' => '٢١:٣٠',
            'keep_days' => '٣',
        ]);

        $this->assertSame($this->drive, $settings->folder);
        $this->assertEquals($settings, $this->driveSetup()->settings());
        $this->assertSame('21:30', $this->driveSetup()->settings()->time);
        $this->assertSame(3, $this->driveSetup()->settings()->keepDays);
        $this->assertSame('https://drive.google.com/drive/folders/abc123', $this->driveSetup()->settings()->link);

        // The check wrote nothing that stays in the folder.
        $this->assertSame([], $this->driveFiles());

        // No folder: no evening copy.
        $this->assertFalse($this->setUpDrive(['folder' => null])->isOn());
        $this->assertFalse($this->driveSetup()->settings()->isOn());
        $this->assertBroken(DriveCopyViolation::OFF, fn () => app(CopyBackupToDrive::class)->handle());
        $this->assertFalse(app(GuardEveningDriveCopy::class)->handle());
    }

    public function test_a_whole_backup_is_put_in_the_folder_under_its_own_name(): void
    {
        $this->setUpDrive();

        $result = app(CopyBackupToDrive::class)->handle();

        $this->assertSame(BackupKind::Evening, $result->backup->kind);
        $this->assertSame('rawda-backup-2026-10-07_170000-evening.zip', $result->backup->name);
        $this->assertSame($this->drive, $result->folder);
        $this->assertSame([$result->backup->name], $this->driveFiles());
        $this->assertSame(
            hash_file('sha256', $result->backup->path),
            hash_file('sha256', $this->drive.DIRECTORY_SEPARATOR.$result->backup->name),
        );

        // The same backup is in the list of this computer, as an evening one.
        $this->assertSame(['evening'], array_map(fn (Backup $backup): string => $backup->kind->value, app(BackupStore::class)->all()));
        $this->assertSame('مسائية (Google Drive)', BackupKind::Evening->label());

        $state = $this->driveSetup()->state();
        $this->assertSame('2026-10-07 17:00:00', $state->copiedAt?->format('Y-m-d H:i:s'));
        $this->assertSame($result->backup->name, $state->copiedName);
        $this->assertFalse($state->hasFailed());
    }

    public function test_copies_older_than_the_days_kept_are_removed_here_and_there_and_nothing_else(): void
    {
        $this->setUpDrive(['keep_days' => 3]);

        $old = $this->place($this->drive, 'rawda-backup-2026-10-04_200000-evening.zip');
        $kept = $this->place($this->drive, 'rawda-backup-2026-10-05_200000-evening.zip');
        $unfinished = $this->place($this->drive, 'rawda-backup-2026-10-06_200000-evening.zip.part');
        // Not the system's evening copies: never touched.
        $this->place($this->drive, 'rawda-backup-2026-09-01_100000-manual.zip');
        $this->place($this->drive, 'ملاحظات.txt');
        $this->place($this->drive, 'rawda-backup-2026-09-01_100000-manual.zip.part');

        File::ensureDirectoryExists(config('rawda.backups.path'));
        $this->place(config('rawda.backups.path'), 'rawda-backup-2026-10-04_200000-evening.zip');
        $this->place(config('rawda.backups.path'), 'rawda-backup-2026-10-04_080000-auto.zip');

        $result = app(CopyBackupToDrive::class)->handle();

        $this->assertSame([basename($old), basename($unfinished)], $result->removed);
        $this->assertSame([], $result->notRemoved);
        $this->assertSame([
            'rawda-backup-2026-09-01_100000-manual.zip',
            'rawda-backup-2026-09-01_100000-manual.zip.part',
            basename($kept),
            'rawda-backup-2026-10-07_170000-evening.zip',
            'ملاحظات.txt',
        ], $this->driveFiles());

        $this->assertSame(
            ['rawda-backup-2026-10-07_170000-evening.zip', 'rawda-backup-2026-10-04_080000-auto.zip'],
            array_map(fn (Backup $backup): string => $backup->name, app(BackupStore::class)->all()),
        );
    }

    public function test_the_copy_is_due_once_every_evening_and_a_missed_evening_is_caught_up(): void
    {
        $this->setUpDrive();
        $guard = fn (): bool => app(GuardEveningDriveCopy::class)->handle();

        // Set up in the afternoon: the first copy goes at once.
        $this->assertTrue($guard());
        $this->travelTo('2026-10-07 17:05:00');
        $this->assertFalse($guard());
        $this->travelTo('2026-10-07 19:59:00');
        $this->assertFalse($guard());

        $this->travelTo('2026-10-07 20:00:00');
        $this->assertTrue($guard());
        $this->travelTo('2026-10-07 23:00:00');
        $this->assertFalse($guard());

        // The laptop was off on the evening of the 8th: the copy goes when it is used again.
        $this->travelTo('2026-10-09 16:00:00');
        $this->assertTrue($guard());
        $this->assertFalse($guard());

        $this->assertSame([
            'rawda-backup-2026-10-07_170000-evening.zip',
            'rawda-backup-2026-10-07_200000-evening.zip',
            'rawda-backup-2026-10-09_160000-evening.zip',
        ], $this->driveFiles());
        $this->assertSame([], $this->adminNotifier->driveFailures);
    }

    public function test_a_copy_that_cannot_be_made_is_told_once_a_day_and_tried_again_later(): void
    {
        $this->setUpDrive();
        // The Google Drive program is not running: its folder is not there.
        File::deleteDirectory($this->drive);
        $guard = fn (): bool => app(GuardEveningDriveCopy::class)->handle();

        $this->assertFalse($guard());
        $this->assertCount(1, $this->adminNotifier->driveFailures);
        $this->assertStringContainsString('لم يُعثر على المجلد', $this->adminNotifier->driveFailures[0]);
        $this->assertStringContainsString('لم يُعثر على المجلد', (string) $this->driveSetup()->state()->failure);
        // No backup was taken for a folder that is not there.
        $this->assertSame([], app(BackupStore::class)->all());

        // Not tried again for half an hour; then tried, and not told twice the same day.
        $this->travelTo('2026-10-07 17:20:00');
        $this->assertFalse($guard());
        $this->assertSame('2026-10-07 17:00:00', $this->driveSetup()->state()->failedAt?->format('Y-m-d H:i:s'));
        $this->travelTo('2026-10-07 17:31:00');
        $this->assertFalse($guard());
        $this->assertSame('2026-10-07 17:31:00', $this->driveSetup()->state()->failedAt?->format('Y-m-d H:i:s'));
        $this->assertCount(1, $this->adminNotifier->driveFailures);

        // The next day the admin is told again.
        $this->travelTo('2026-10-08 09:00:00');
        $this->assertFalse($guard());
        $this->assertCount(2, $this->adminNotifier->driveFailures);

        // The program runs again: the copy goes, and the failure is forgotten.
        File::ensureDirectoryExists($this->drive);
        $this->travelTo('2026-10-08 09:31:00');
        $this->assertTrue($guard());
        $this->assertFalse($this->driveSetup()->state()->hasFailed());
        $this->assertSame(['rawda-backup-2026-10-08_093100-evening.zip'], $this->driveFiles());
    }

    public function test_old_copies_that_cannot_be_removed_are_told_for_the_admin_to_remove(): void
    {
        if (PHP_OS_FAMILY !== 'Windows') {
            $this->markTestSkipped('A read-only file cannot be removed on Windows only.');
        }

        $this->setUpDrive(['keep_days' => 1]);
        $stuck = $this->place($this->drive, 'rawda-backup-2026-10-01_200000-evening.zip');
        chmod($stuck, 0444);

        $this->assertTrue(app(GuardEveningDriveCopy::class)->handle());

        $this->assertSame([['names' => [basename($stuck)], 'folder' => $this->drive]], $this->adminNotifier->driveLeftovers);
        $this->assertFileExists($stuck);

        // Told once a day.
        $this->travelTo('2026-10-07 20:00:00');
        $this->assertTrue(app(GuardEveningDriveCopy::class)->handle());
        $this->assertCount(1, $this->adminNotifier->driveLeftovers);
    }

    public function test_the_demo_never_puts_copies_in_a_drive_folder(): void
    {
        $this->driveSetup()->save(new DriveCopySettings(folder: $this->drive));
        $this->app->instance(DemoMode::class, new DemoMode('rawda_demo', 8010));

        $this->assertBroken(DriveCopyViolation::DEMO, fn () => $this->setUpDrive());
        $this->assertBroken(DriveCopyViolation::DEMO, fn () => app(CopyBackupToDrive::class)->handle());
        $this->assertFalse(app(GuardEveningDriveCopy::class)->handle());
        $this->assertSame([], $this->driveFiles());
    }

    public function test_only_the_systems_own_copies_count_as_copies_in_the_folder(): void
    {
        $this->place($this->drive, 'rawda-backup-2026-10-05_200000-evening.zip');
        $this->place($this->drive, 'rawda-backup-2026-10-05_200000-manual.zip');
        $this->place($this->drive, 'rawda-backup-2026-13-05_200000-evening.zip');

        $folder = new DriveFolder($this->drive);

        $this->assertSame(['rawda-backup-2026-10-05_200000-evening.zip'], array_map(fn (Backup $backup): string => $backup->name, $folder->copies()));
        File::ensureDirectoryExists($this->drive.DIRECTORY_SEPARATOR.'sub');

        $this->assertTrue($folder->isWithin($this->drive.DIRECTORY_SEPARATOR));
        $this->assertTrue((new DriveFolder($this->drive.DIRECTORY_SEPARATOR.'sub'))->isWithin($this->drive));
        $this->assertFalse((new DriveFolder($this->drive.'-other'))->isWithin($this->drive));
    }
}
