<?php

namespace Tests\Feature\Panel;

use App\Actions\Backups\DriveCopyViolation;
use App\Filament\Pages\Backups;
use App\Services\Backup\DriveCopySettings;
use App\Services\Backup\DriveCopySetup;
use App\Services\Demo\DemoMode;
use Filament\Actions\Testing\TestAction;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Livewire\Livewire;

/**
 * The evening copy for Google Drive on the backups page: setting it up,
 * putting a copy there now, and the tick that keeps it on time while a
 * page of the panel is open.
 */
class DriveCopyPanelTest extends PanelTestCase
{
    private string $drive;

    protected function setUp(): void
    {
        parent::setUp();

        File::deleteDirectory(config('rawda.backups.path'));

        $this->drive = sys_get_temp_dir().DIRECTORY_SEPARATOR.'rawda-drive-'.Str::lower(Str::random(8));
        File::ensureDirectoryExists($this->drive);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->drive);

        parent::tearDown();
    }

    private static function inCallout(string $action): TestAction
    {
        return TestAction::make($action)->schemaComponent(Backups::DRIVE_CALLOUT, 'content');
    }

    /**
     * @return list<string>
     */
    private function driveFiles(): array
    {
        return array_values(array_diff(scandir($this->drive) ?: [], ['.', '..']));
    }

    public function test_the_evening_copy_is_set_up_and_a_copy_put_there_from_the_backups_page(): void
    {
        Livewire::test(Backups::class)
            ->assertOk()
            ->assertSee('نسخة كل مساء في Google Drive')
            ->assertSee('غير مفعّلة')
            ->assertActionVisible(self::inCallout('driveSettings'))
            ->assertActionHidden(self::inCallout('driveCopyNow'))
            ->assertActionHidden(self::inCallout('openDrive'))
            // A folder that is not on this computer is refused, with the reason.
            ->callAction(self::inCallout('driveSettings'), data: ['folder' => $this->drive.DIRECTORY_SEPARATOR.'غير موجود', 'time' => '20:00', 'keep_days' => 7])
            ->assertNotified(DriveCopyViolation::folderMissing($this->drive.DIRECTORY_SEPARATOR.'غير موجود')->getMessage());

        $this->assertFalse(app(DriveCopySetup::class)->settings()->isOn());

        Livewire::test(Backups::class)
            ->mountAction(self::inCallout('driveSettings'))
            ->assertActionDataSet(['folder' => null, 'time' => DriveCopySettings::DEFAULT_TIME, 'keep_days' => DriveCopySettings::DEFAULT_KEEP_DAYS])
            ->setActionData([
                'folder' => $this->drive,
                'link' => 'https://drive.google.com/drive/folders/abc123',
                'time' => '21:00',
                'keep_days' => 5,
            ])
            ->callMountedAction()
            ->assertHasNoActionErrors()
            ->assertNotified('فُعّلت النسخة المسائية في Google Drive');

        $page = Livewire::test(Backups::class)
            ->assertSee('كل يوم بعد الساعة')
            ->assertSee($this->drive)
            ->assertSee('5 أيام')
            ->assertSee('لم توضع أي نسخة بعد')
            ->assertActionVisible(self::inCallout('driveCopyNow'))
            ->assertActionHasUrl(self::inCallout('openDrive'), 'https://drive.google.com/drive/folders/abc123')
            ->callAction(self::inCallout('driveCopyNow'))
            ->assertNotified('وُضعت النسخة في مجلد Google Drive')
            ->assertCanSeeTableRecords(['2026-10-07_170000-evening'])
            ->assertTableColumnStateSet('kind', 'مسائية (Google Drive)', '2026-10-07_170000-evening');

        $this->assertSame(['rawda-backup-2026-10-07_170000-evening.zip'], $this->driveFiles());

        $page->assertSee('آخر نسخة وُضعت في');

        // An empty folder turns it off.
        Livewire::test(Backups::class)
            ->callAction(self::inCallout('driveSettings'), data: ['folder' => null, 'link' => null, 'time' => '21:00', 'keep_days' => 5])
            ->assertNotified('أُوقفت النسخة المسائية في Google Drive');

        $this->assertFalse(app(DriveCopySetup::class)->settings()->isOn());
    }

    public function test_an_open_page_ticks_and_the_tick_puts_the_copy_when_it_is_due(): void
    {
        config()->set('rawda.backups.drive', true);

        // Off: the pages do not tick, and a tick does nothing.
        $this->get(Backups::getUrl())->assertOk()->assertDontSee(route('filament.admin.tick'));
        $this->get(route('filament.admin.tick'))->assertNoContent();
        $this->assertSame([], $this->driveFiles());

        app(DriveCopySetup::class)->save(new DriveCopySettings(folder: $this->drive));

        $this->get(route('filament.admin.tick'))->assertNoContent();
        $this->assertSame(['rawda-backup-2026-10-07_170000-evening.zip'], $this->driveFiles());

        // On: every page ticks. The copy is due again only in the evening.
        $this->get(Backups::getUrl())->assertOk()->assertSee('<script data-tick="'.e(route('filament.admin.tick')).'">', escape: false);
        $this->get(route('filament.admin.tick'))->assertNoContent();
        $this->assertCount(1, $this->driveFiles());

        $this->travelTo('2026-10-07 20:01:00');
        $this->get(route('filament.admin.tick'))->assertNoContent();
        $this->assertCount(2, $this->driveFiles());

        // Only the signed-in admin.
        auth()->logout();
        $this->get(route('filament.admin.tick'))->assertRedirect();
    }

    public function test_the_demo_shows_that_it_never_copies_to_drive(): void
    {
        $this->app->instance(DemoMode::class, new DemoMode('rawda_demo', 8010));

        Livewire::test(Backups::class)
            ->assertSee('النسخة التجريبية لا تضع نسخاً في Google Drive')
            ->assertActionHidden(self::inCallout('driveSettings'))
            ->assertActionHidden(self::inCallout('driveCopyNow'));
    }
}
