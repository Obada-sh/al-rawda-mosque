<?php

namespace Tests\Feature;

use App\Actions\Attendance\ScanCard;
use App\Actions\Backups\BackupViolation;
use App\Actions\Backups\CreateBackup;
use App\Actions\Backups\DeleteBackup;
use App\Actions\Backups\GuardDailyBackup;
use App\Actions\Backups\ImportBackup;
use App\Actions\Backups\RestoreBackup;
use App\Actions\Backups\RunDailyBackup;
use App\Actions\Circles\SaveCircle;
use App\Actions\Market\SaveMarketItem;
use App\Actions\Students\SaveStudent;
use App\Actions\Teachers\SaveTeacher;
use App\Contracts\SettingsStore;
use App\Enums\BackupKind;
use App\Models\Activity;
use App\Models\Attendance;
use App\Models\Circle;
use App\Models\Course;
use App\Models\Expense;
use App\Models\Market;
use App\Models\MarketItem;
use App\Models\PointTransaction;
use App\Models\Recitation;
use App\Models\Student;
use App\Models\StudentPage;
use App\Models\TeacherAttendance;
use App\Models\TeacherCard;
use App\Services\Backup\Backup;
use App\Services\Backup\BackupArchive;
use App\Services\Backup\BackupStore;
use App\Services\Backup\DatabaseDumper;
use App\Services\Circles\CircleLogos;
use App\Services\Export\LeadersTemplates;
use App\Services\Market\MarketItemPictures;
use App\Services\Students\StudentPhotos;
use App\Services\Teachers\TeacherCards;
use App\Services\Teachers\TeacherPhotos;
use App\Support\SettingKey;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\BuildsDomain;
use Tests\Concerns\SeedsReportWeek;
use Tests\Support\Pictures;
use Tests\TestCase;
use ZipArchive;

/**
 * Backups: taking them, keeping the newest, and restoring them.
 *
 * A restore replaces the tables of the database, which cannot happen
 * inside the transaction most tests run in: these tests rebuild the
 * testing database before each of them instead.
 */
class BackupTest extends TestCase
{
    use BuildsDomain;
    use DatabaseMigrations;
    use SeedsReportWeek;

    /** A text that would break a careless backup: quotes, a backslash, a line break and something that looks like SQL. */
    private const AWKWARD = "سطر أول\nسطر 'ثانٍ' \\ \"بين قوسين\"; DROP TABLE students; -- %";

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-10-07 17:00:00');

        File::deleteDirectory(config('rawda.backups.path'));
    }

    private function store(): BackupStore
    {
        return app(BackupStore::class);
    }

    /**
     * @return list<string> the kinds of the backups in the folder, the newest first
     */
    private function kinds(): array
    {
        return array_map(fn (Backup $backup): string => $backup->kind->value, $this->store()->all());
    }

    /**
     * A copy of a backup whose parts went through an edit, with a
     * manifest that matches the edited database again.
     *
     * @param  callable(string): string  $editDatabase
     * @param  (callable(array<string, mixed>): array<string, mixed>)|null  $editManifest
     */
    private function altered(Backup $backup, string $second, callable $editDatabase, ?callable $editManifest = null, bool $refreshFingerprint = true): string
    {
        $source = new ZipArchive;
        $source->open($backup->path, ZipArchive::RDONLY);
        $database = $editDatabase((string) $source->getFromName(BackupArchive::DATABASE));
        $manifest = json_decode((string) $source->getFromName(BackupArchive::MANIFEST), true);
        $source->close();

        if ($refreshFingerprint) {
            $manifest['database']['sha256'] = hash('sha256', $database);
        }

        $manifest = $editManifest === null ? $manifest : $editManifest($manifest);

        $name = "rawda-backup-2026-10-07_1800{$second}-uploaded.zip";

        $zip = new ZipArchive;
        $zip->open($this->store()->directory().DIRECTORY_SEPARATOR.$name, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString(BackupArchive::MANIFEST, (string) json_encode($manifest));
        $zip->addFromString(BackupArchive::DATABASE, $database);
        $zip->close();

        return $name;
    }

    public function test_a_backup_holds_everything_and_restoring_it_brings_everything_back(): void
    {
        $this->seedReportWeek();
        app(SettingsStore::class)->set(SettingKey::MOSQUE_NAME, 'جامع الروضة');
        app(LeadersTemplates::class)->replaceWith(base_path('tests/Fixtures/slides/numbered-3.pptx'));
        $expense = $this->spend(['item_name' => 'تمر', 'notes' => self::AWKWARD]);

        $backup = app(CreateBackup::class)->handle();

        $this->assertSame(BackupKind::Manual, $backup->kind);
        $this->assertSame('rawda-backup-2026-10-07_170000-manual.zip', $backup->name);
        $this->assertSame('2026-10-07 17:00:00', $backup->takenAt->toDateTimeString());
        $this->assertFileExists($backup->path);
        $this->assertSame($backup->name, $this->store()->all()[0]->name);

        // The label of the backup says what it holds.
        $archive = BackupArchive::open($backup->path);
        $archive->verify();
        $this->assertSame('2026-10-07T17:00:00+03:00', $archive->manifest->takenAt->toIso8601String());
        $this->assertSame(3, $archive->manifest->tables['students']);
        $this->assertSame(Attendance::query()->count(), $archive->manifest->tables['attendances']);
        $this->assertSame(30, $archive->manifest->tables['quran_juz']);
        $this->assertContains('2026_10_07_100003_create_students_table', $archive->manifest->migrations);
        $this->assertSame(['slide-templates/leaders.pptx'], array_keys($archive->manifest->files));

        // The database is ordinary SQL, one statement on each line.
        $sql = (string) stream_get_contents($archive->database());
        $this->assertStringStartsWith(DatabaseDumper::HEADER."\n", $sql);
        $this->assertStringContainsString('CREATE TABLE `students` (', $sql);
        $this->assertStringContainsString('جامع الروضة', $sql);
        $this->assertStringEndsWith(DatabaseDumper::LAST_STATEMENT."\n", $sql);
        unset($archive);

        // The same backup, brought back from a USB stick, is listed at the moment its data was copied.
        $this->travelTo('2026-10-07 17:05:00');
        $imported = app(ImportBackup::class)->handle([
            'backup' => UploadedFile::fake()->createWithContent('from-the-stick.zip', (string) file_get_contents($backup->path)),
        ]);
        $this->assertSame('rawda-backup-2026-10-07_170000-uploaded.zip', $imported->name);
        $this->assertFileEquals($backup->path, $imported->path);

        // Later, things change: a student is added, another renamed, records are removed...
        $this->travelTo('2026-10-08 09:00:00');
        $newcomer = $this->enroll(Student::factory()->create(['full_name' => 'دخيل']), $this->course, $this->circleB);
        $this->recite($newcomer, 1, 20, '2026-10-08');
        app(SaveStudent::class)->handle(['full_name' => 'أحمد المعدَّل'], $this->ahmad->student);
        $expense->delete();
        app(SettingsStore::class)->set(SettingKey::MOSQUE_NAME, 'اسم آخر');
        app(LeadersTemplates::class)->restoreDefault();

        $before = app(RestoreBackup::class)->handle(['name' => $backup->name]);

        // ...and the restore brings back the day of the backup, down to the last character.
        $this->assertSame(['أحمد', 'بلال', 'خالد'], Student::query()->orderBy('id')->pluck('full_name')->all());
        $this->assertSame(self::AWKWARD, Expense::query()->sole()->notes);
        $this->assertSame('تمر', Expense::query()->sole()->item_name);
        $this->assertSame('جامع الروضة', app(SettingsStore::class)->get(SettingKey::MOSQUE_NAME));
        $this->assertSame(3, app(LeadersTemplates::class)->custom()?->itemsPerSlide());
        $this->assertSame(16, (int) StudentPage::query()->count());
        $this->assertSame(3, Recitation::query()->count());
        $this->assertSame(27, (int) PointTransaction::query()->where('enrollment_id', $this->ahmad->getKey())->sum('points'));
        $this->assertSame('2026-10-01', Attendance::query()->orderBy('id')->firstOrFail()->session->session_date->toDateString());

        // Numbering goes on from where the backup left it: the newcomer's number was never given in that state.
        $this->assertSame($newcomer->student_id, Student::factory()->create()->getKey());

        // The state that was replaced is kept, and restoring it undoes the restore.
        $this->assertSame(BackupKind::BeforeRestore, $before->kind);
        $this->assertSame(['before-restore', 'uploaded', 'manual'], $this->kinds());

        $this->travelTo('2026-10-08 09:10:00');
        app(RestoreBackup::class)->handle(['name' => $before->name]);

        $this->assertSame(['أحمد المعدَّل', 'بلال', 'خالد', 'دخيل'], Student::query()->orderBy('id')->pluck('full_name')->all());
        $this->assertSame(0, Expense::query()->count());
        $this->assertSame('اسم آخر', app(SettingsStore::class)->get(SettingKey::MOSQUE_NAME));
        $this->assertFalse(app(LeadersTemplates::class)->hasCustom());
        $this->assertSame(['before-restore', 'before-restore', 'uploaded', 'manual'], $this->kinds());
    }

    public function test_the_photos_the_logos_and_the_market_pictures_travel_with_the_backup(): void
    {
        $this->travelTo('2026-10-07 17:00:00');
        $student = app(SaveStudent::class)->handle(['full_name' => 'أحمد', 'photo' => Pictures::png()]);
        $photo = $student->photo_path;
        $teacher = app(SaveTeacher::class)->handle(['full_name' => 'الشيخ ياسر', 'photo' => Pictures::png()]);
        $course = Course::factory()->create();
        $circle = app(SaveCircle::class)->handle($course, [
            'name' => 'تاج الوقار',
            'supervisor_teacher_id' => $teacher->getKey(),
            'logo' => Pictures::png('logo.png'),
        ]);
        $logo = $circle->logo_path;
        $item = app(SaveMarketItem::class)->handle(Market::factory()->create(['course_id' => $course->getKey()]), [
            'name' => 'قلم',
            'price' => 5,
            'image' => Pictures::png('pen.png'),
        ]);
        $picture = $item->image_path;
        $teacherCard = app(TeacherCards::class)->of($teacher, $course)->card_code;
        app(ScanCard::class)->handle($teacherCard);
        $this->holdActivity(overrides: ['title' => 'رحلة الجامع']);

        $backup = app(CreateBackup::class)->handle();

        $archive = BackupArchive::open($backup->path);
        $archive->verify();
        $this->assertEqualsCanonicalizing([$photo, $teacher->photo_path, $logo, $picture], array_keys($archive->manifest->files));
        $this->assertSame(1, $archive->manifest->tables['activities']);
        $this->assertSame(1, $archive->manifest->tables['teacher_attendances']);
        unset($archive);

        // Later the photo is replaced, another student gets one, the logo is removed
        // and the teacher's attendance deleted...
        $this->travelTo('2026-10-08 09:00:00');
        app(SaveStudent::class)->handle(['full_name' => 'أحمد', 'photo' => Pictures::png()], $student);
        $this->assertNotSame($photo, $student->photo_path);
        $newcomer = app(SaveStudent::class)->handle(['full_name' => 'دخيل', 'photo' => Pictures::png()]);
        app(SaveCircle::class)->handle($circle->course, ['name' => 'تاج الوقار', 'remove_logo' => true], $circle);
        app(SaveMarketItem::class)->handle($item->market, ['name' => 'قلم', 'price' => 5, 'remove_image' => true], $item);
        TeacherAttendance::query()->delete();

        app(RestoreBackup::class)->handle(['name' => $backup->name]);

        // ...and the restore brings back the files of that day, and only those.
        $files = Storage::disk(StudentPhotos::DISK);
        $this->assertSame([$photo], $files->files(StudentPhotos::FOLDER));
        $this->assertSame($photo, Student::query()->sole()->photo_path);
        $files->assertMissing($newcomer->photo_path);
        $this->assertSame([$teacher->photo_path], $files->files(TeacherPhotos::FOLDER));
        $this->assertSame([$logo], $files->files(CircleLogos::FOLDER));
        $this->assertSame($logo, Circle::query()->sole()->logo_path);
        $this->assertSame([$picture], $files->files(MarketItemPictures::FOLDER));
        $this->assertSame($picture, MarketItem::query()->sole()->image_path);
        $this->assertSame($teacherCard, TeacherCard::query()->sole()->card_code);
        $this->assertSame(1, TeacherAttendance::query()->count());
        $this->assertSame('رحلة الجامع', Activity::query()->sole()->title);
    }

    public function test_the_automatic_backup_is_taken_once_a_day_and_only_the_newest_are_kept(): void
    {
        config(['rawda.backups.keep' => 2]);

        $daily = app(RunDailyBackup::class);
        $manual = app(CreateBackup::class)->handle();

        $first = $daily->handle();
        $this->assertSame(BackupKind::Automatic, $first?->kind);

        // The same day, later: nothing more.
        $this->travelTo('2026-10-07 21:00:00');
        $this->assertNull($daily->handle());

        $this->travelTo('2026-10-08 08:00:00');
        $second = $daily->handle();

        $this->travelTo('2026-10-09 08:00:00');
        $third = $daily->handle();

        // Two automatic backups are kept; the one taken by hand is never dropped.
        $this->assertSame(
            [$third?->name, $second?->name, $manual->name],
            array_map(fn (Backup $backup): string => $backup->name, $this->store()->all()),
        );
        $this->assertFileDoesNotExist((string) $first?->path);

        // A backup is removed by its name, and only once.
        app(DeleteBackup::class)->handle(['name' => $manual->name]);
        $this->assertFileDoesNotExist($manual->path);
        $this->assertViolates(BackupViolation::NOT_FOUND, fn () => app(DeleteBackup::class)->handle(['name' => $manual->name]));
    }

    public function test_a_backup_that_cannot_be_taken_is_reported_once_a_day_without_stopping_the_admin(): void
    {
        // A folder that cannot exist: its parent is a file.
        File::ensureDirectoryExists(storage_path('framework/testing'));
        File::put(storage_path('framework/testing/not-a-folder'), 'x');
        config(['rawda.backups.path' => storage_path('framework/testing/not-a-folder/backups')]);

        $this->assertViolates(BackupViolation::FOLDER_NOT_WRITABLE, fn () => app(CreateBackup::class)->handle());

        $guard = app(GuardDailyBackup::class);

        $this->assertFalse($guard->handle());
        $this->assertFalse($guard->handle());
        $this->assertSame(1, $this->adminNotifier->backupFailures);

        $this->travelTo('2026-10-08 08:00:00');
        $this->assertFalse(app(GuardDailyBackup::class)->handle());
        $this->assertSame(2, $this->adminNotifier->backupFailures);

        // Once the folder can be written again, the backup of the day is taken.
        config(['rawda.backups.path' => storage_path('framework/testing/backups')]);
        $this->assertTrue(app(GuardDailyBackup::class)->handle());
        $this->assertSame(['auto'], $this->kinds());
        $this->assertSame(2, $this->adminNotifier->backupFailures);

        File::delete(storage_path('framework/testing/not-a-folder'));
    }

    public function test_a_file_that_is_not_a_whole_backup_of_this_system_is_refused_before_anything_is_touched(): void
    {
        $student = Student::factory()->create(['full_name' => 'أحمد']);
        $backup = app(CreateBackup::class)->handle();
        $restore = app(RestoreBackup::class);

        // Its database was altered after the backup was taken.
        $altered = $this->altered($backup, '01', fn (string $sql): string => str_replace('أحمد', 'غيره', $sql), refreshFingerprint: false);
        $this->assertViolates(BackupViolation::DAMAGED, fn () => $restore->handle(['name' => $altered]));

        // It carries a statement the system never writes, even with a matching fingerprint.
        $foreign = $this->altered($backup, '02', fn (string $sql): string => str_replace(
            DatabaseDumper::LAST_STATEMENT,
            "DELETE FROM `students`;\n".DatabaseDumper::LAST_STATEMENT,
            $sql,
        ));
        $this->assertViolates(BackupViolation::DAMAGED, fn () => $restore->handle(['name' => $foreign]));

        // It was cut short.
        $cut = $this->altered($backup, '03', fn (string $sql): string => substr($sql, 0, (int) (strlen($sql) / 2)));
        $this->assertViolates(BackupViolation::DAMAGED, fn () => $restore->handle(['name' => $cut]));

        // It was taken by a newer version of the system.
        $newer = $this->altered($backup, '04', fn (string $sql): string => $sql, function (array $manifest): array {
            $manifest['database']['migrations'][] = '2099_01_01_000000_something_from_the_future';

            return $manifest;
        });
        $this->assertViolates(BackupViolation::NEWER_VERSION, fn () => $restore->handle(['name' => $newer]));

        // It is not a backup at all.
        File::put($this->store()->directory().'/rawda-backup-2026-10-07_180005-uploaded.zip', 'just some text');
        $this->assertViolates(BackupViolation::NOT_A_BACKUP, fn () => $restore->handle(['name' => 'rawda-backup-2026-10-07_180005-uploaded.zip']));

        // It is not in the folder, or its name is not the name of a backup.
        $this->assertViolates(BackupViolation::NOT_FOUND, fn () => $restore->handle(['name' => 'rawda-backup-2026-01-01_000000-manual.zip']));

        foreach (['../../.env', 'backup.zip', '', null] as $name) {
            try {
                $restore->handle(['name' => $name]);
                $this->fail('A name that is not the name of a backup was accepted.');
            } catch (ValidationException $exception) {
                $this->assertSame(['name'], array_keys($exception->errors()));
            }
        }

        // The same checks guard what is brought from outside.
        $import = app(ImportBackup::class);
        $this->assertViolates(BackupViolation::NOT_A_BACKUP, fn () => $import->handle([
            'backup' => UploadedFile::fake()->createWithContent('backup.zip', 'just some text'),
        ]));
        $this->assertViolates(BackupViolation::DAMAGED, fn () => $import->handle([
            'backup' => UploadedFile::fake()->createWithContent('backup.zip', (string) file_get_contents($this->store()->directory().'/'.$foreign)),
        ]));

        foreach ([['backup' => UploadedFile::fake()->create('backup.sql', 10)], []] as $input) {
            try {
                $import->handle($input);
                $this->fail('An upload that is not a .zip file was accepted.');
            } catch (ValidationException $exception) {
                $this->assertSame(['backup'], array_keys($exception->errors()));
            }
        }

        // Nothing was restored, and no backup "before restore" was needed.
        $this->assertSame('أحمد', $student->fresh()->full_name);
        $this->assertNotContains('before-restore', $this->kinds());
    }

    public function test_a_restore_that_fails_half_way_puts_back_what_was_there(): void
    {
        Student::factory()->create(['full_name' => 'أحمد']);
        $backup = app(CreateBackup::class)->handle();

        // Every line is one the system writes, but the same rows come twice: the second time fails.
        $twice = $this->altered($backup, '01', function (string $sql): string {
            preg_match('/^INSERT INTO `students` .*$/m', $sql, $insert);

            return str_replace($insert[0], $insert[0]."\n".$insert[0], $sql);
        });

        Student::factory()->create(['full_name' => 'بلال']);
        app(SettingsStore::class)->set(SettingKey::MOSQUE_NAME, 'جامع الروضة');

        $this->travelTo('2026-10-07 19:00:00');
        $this->assertViolates(BackupViolation::RESTORE_FAILED, fn () => app(RestoreBackup::class)->handle(['name' => $twice]));

        // Everything is as it was a moment before the attempt.
        $this->assertSame(['أحمد', 'بلال'], Student::query()->orderBy('id')->pluck('full_name')->all());
        $this->assertSame('جامع الروضة', app(SettingsStore::class)->get(SettingKey::MOSQUE_NAME));
        $this->assertContains('before-restore', $this->kinds());
    }

    public function test_the_system_is_prepared_and_backed_up_from_the_command_line(): void
    {
        $this->artisan('rawda:backup')
            ->expectsOutputToContain('Backup written:')
            ->assertSuccessful();

        $this->artisan('rawda:backup', ['--daily' => true])
            ->expectsOutputToContain('Backup written:')
            ->assertSuccessful();

        $this->artisan('rawda:backup', ['--daily' => true])
            ->expectsOutputToContain('Today already has its automatic backup.')
            ->assertSuccessful();

        // Both were taken in the same second: the list is by time, then by name.
        $this->assertSame(['manual', 'auto'], $this->kinds());

        // start.bat runs this before the server: the database is up to date, the day has its backup.
        $this->artisan('rawda:prepare', ['--wait' => 0])
            ->expectsOutputToContain('Database: up to date.')
            ->expectsOutputToContain('Daily backup: already taken today.')
            ->expectsOutputToContain('The system is ready.')
            ->assertSuccessful();

        $this->travelTo('2026-10-08 08:00:00');
        $this->artisan('rawda:prepare', ['--wait' => 0])->assertSuccessful();

        $this->assertSame(['auto', 'manual', 'auto'], $this->kinds());
    }
}
