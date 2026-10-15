<?php

namespace Tests\Feature\Panel;

use App\Actions\Backups\CreateBackup;
use App\Filament\Pages\Backups;
use App\Models\Student;
use App\Models\User;
use App\Services\Backup\Backup;
use App\Services\Backup\BackupStore;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The backups page of the panel. A restore replaces the tables of the
 * database, so these tests rebuild the testing database before each of
 * them instead of running inside a transaction.
 */
class BackupsPanelTest extends TestCase
{
    use DatabaseMigrations;

    private const MANUAL = '2026-10-07_170000-manual';

    private const UPLOADED = '2026-10-07_170000-uploaded';

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-10-07 17:00:00');

        File::deleteDirectory(config('rawda.backups.path'));

        $this->admin = User::factory()->create();
        $this->actingAs($this->admin);
    }

    private static function fileOf(string $key): string
    {
        return "rawda-backup-{$key}.zip";
    }

    /**
     * @return list<string> the kinds of the backups in the folder, the newest first
     */
    private function kinds(): array
    {
        return array_map(fn (Backup $backup): string => $backup->kind->value, app(BackupStore::class)->all());
    }

    public function test_backups_are_taken_listed_saved_added_and_deleted_from_the_panel(): void
    {
        Livewire::test(Backups::class)
            ->assertOk()
            ->assertSee('احفظ نسخة خارج هذا الحاسوب')
            ->assertSee('لا توجد نسخ احتياطية بعد')
            ->callAction('create')
            ->assertNotified('أُخذت النسخة الاحتياطية')
            ->assertCanSeeTableRecords([self::MANUAL])
            ->assertTableColumnStateSet('taken_at', '07/10/2026 17:00', self::MANUAL)
            ->assertTableColumnStateSet('kind', 'يدوية', self::MANUAL);

        // The file is handed to the browser, to be saved on a USB stick.
        $download = route('filament.admin.backups.download', ['name' => self::fileOf(self::MANUAL)]);

        $this->get($download)->assertOk()->assertDownload(self::fileOf(self::MANUAL));
        $this->get(route('filament.admin.backups.download', ['name' => self::fileOf('2026-01-01_000000-manual')]))->assertNotFound();
        $this->get(route('filament.admin.backups.download', ['name' => 'passwords.txt']))->assertNotFound();

        // Only to the admin.
        Auth::logout();
        $this->get($download)->assertRedirect(Filament::getLoginUrl());
        $this->actingAs($this->admin);

        // A backup brought back from the stick joins the list; nothing is restored by adding it.
        $stick = (string) file_get_contents(app(BackupStore::class)->find(self::fileOf(self::MANUAL))->path);

        Livewire::test(Backups::class)
            ->callAction('import', data: ['backup' => UploadedFile::fake()->createWithContent('from-the-stick.zip', $stick)])
            ->assertHasNoActionErrors()
            ->assertNotified('أُضيفت النسخة إلى القائمة')
            ->assertCanSeeTableRecords([self::UPLOADED, self::MANUAL], inOrder: true)
            ->assertTableColumnStateSet('kind', 'مرفوعة', self::UPLOADED);

        Livewire::test(Backups::class)
            ->callAction('import', data: ['backup' => UploadedFile::fake()->create('notes.txt', 10)])
            ->assertHasActionErrors(['backup']);

        Livewire::test(Backups::class)
            ->callAction('import', data: ['backup' => UploadedFile::fake()->createWithContent('other.zip', 'not a backup')])
            ->assertNotified('هذا الملف ليس نسخة احتياطية من هذا النظام.')
            ->assertActionMounted('import');

        Livewire::test(Backups::class)
            ->callAction(TestAction::make('delete')->table(self::UPLOADED))
            ->assertNotified('حُذفت النسخة')
            ->assertCanSeeTableRecords([self::MANUAL])
            ->assertCanNotSeeTableRecords([self::UPLOADED]);

        $this->assertSame(['manual'], $this->kinds());
    }

    public function test_opening_the_panel_takes_the_backup_of_the_day_once(): void
    {
        config(['rawda.backups.automatic' => true]);

        $this->get(Backups::getUrl())->assertOk();
        $this->get(Backups::getUrl())->assertOk()->assertSee('تلقائية');

        $this->assertSame(['auto'], $this->kinds());

        $this->travelTo('2026-10-08 08:00:00');
        $this->get(Filament::getUrl())->assertOk();

        $this->assertSame(['auto', 'auto'], $this->kinds());
    }

    public function test_a_backup_is_restored_from_the_panel(): void
    {
        $student = Student::factory()->create(['full_name' => 'أحمد']);
        $backup = app(CreateBackup::class)->handle();

        $student->update(['full_name' => 'اسم كُتب بالخطأ']);
        Student::factory()->create(['full_name' => 'طالب أُضيف بعد النسخة']);

        $this->travelTo('2026-10-07 18:00:00');

        Livewire::test(Backups::class)
            ->callAction(TestAction::make('restore')->table(self::MANUAL))
            ->assertNotified('تمت الاستعادة')
            ->assertRedirect(Filament::getUrl());

        $this->assertSame(['أحمد'], Student::query()->pluck('full_name')->all());
        $this->assertSame(['before-restore', 'manual'], $this->kinds());
        // The admin who restored is the admin of the restored system: the backup was taken with their account.
        $this->assertTrue(User::query()->whereKey($this->admin->getKey())->exists());

        // A file that is not a backup is refused with a reason, and nothing is replaced.
        File::put(app(BackupStore::class)->directory().'/'.self::fileOf('2026-10-07_180500-uploaded'), 'not a backup');

        Livewire::test(Backups::class)
            ->callAction(TestAction::make('restore')->table('2026-10-07_180500-uploaded'))
            ->assertNotified('هذا الملف ليس نسخة احتياطية من هذا النظام.')
            ->assertNoRedirect();

        $this->assertSame(['أحمد'], Student::query()->pluck('full_name')->all());
        $this->assertSame($backup->name, self::fileOf(self::MANUAL));
    }
}
