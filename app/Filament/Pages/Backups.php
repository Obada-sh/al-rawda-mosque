<?php

namespace App\Filament\Pages;

use App\Actions\Backups\CopyBackupToDrive;
use App\Actions\Backups\CreateBackup;
use App\Actions\Backups\DeleteBackup;
use App\Actions\Backups\DriveCopyResult;
use App\Actions\Backups\ImportBackup;
use App\Actions\Backups\RestoreBackup;
use App\Actions\Backups\SaveDriveCopySettings;
use App\Filament\Enums\PanelGroup;
use App\Filament\Support\Attempt;
use App\Filament\Support\Fields\FileToCheck;
use App\Filament\Support\Presenters\BackupPresenter;
use App\Filament\Support\Presenters\DriveCopyPresenter;
use App\Http\Requests\Backups\BackupUploadRequest;
use App\Services\Backup\Backup;
use App\Services\Backup\BackupStore;
use App\Services\Backup\DriveCopySettings;
use App\Services\Backup\DriveCopySetup;
use App\Services\Demo\DemoMode;
use App\Support\ArabicNoun;
use App\Support\FileSize;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Collection;
use Illuminate\Support\HtmlString;
use UnitEnum;

/**
 * The backups of the system: the list of the files in the backups
 * folder, and the buttons to take one now, save one outside the computer,
 * bring one back from outside, restore one and delete one; and the
 * evening copy for Google Drive: its set-up, how it went, and a button to
 * put a copy there now.
 */
class Backups extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|UnitEnum|null $navigationGroup = PanelGroup::Settings;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArchiveBox;

    protected static ?int $navigationSort = 30;

    protected static ?string $navigationLabel = 'النسخ الاحتياطي';

    protected static ?string $title = 'النسخ الاحتياطي';

    protected static ?string $slug = 'backups';

    /** The key of the part of the page about the evening copy for Google Drive, whose buttons are its own. */
    public const DRIVE_CALLOUT = 'driveCopy';

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                Callout::make('احفظ نسخة خارج هذا الحاسوب')
                    ->description(fn (BackupStore $store): HtmlString => self::advice($store))
                    ->info(),
                Callout::make('نسخة كل مساء في Google Drive')
                    ->key(self::DRIVE_CALLOUT)
                    ->description(fn (DriveCopySetup $setup, DemoMode $demo): HtmlString => DriveCopyPresenter::summary($setup->settings(), $setup->state(), $demo->isOn()))
                    ->status(fn (DriveCopySetup $setup, DemoMode $demo): string => DriveCopyPresenter::status($setup->settings(), $setup->state(), $demo->isOn()))
                    ->icon(Heroicon::OutlinedCloudArrowUp)
                    ->actions([
                        $this->driveSettingsAction(),
                        $this->driveCopyNowAction(),
                        $this->openDriveAction(),
                    ]),
                EmbeddedTable::make(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->records(fn (BackupStore $store): Collection => collect($store->all())->mapWithKeys(
                fn (Backup $backup): array => [BackupPresenter::key($backup) => BackupPresenter::row($backup)],
            ))
            ->columns([
                TextColumn::make('taken_at')
                    ->label('أُخذت في')
                    ->weight('bold'),
                TextColumn::make('kind')
                    ->label('النوع')
                    ->badge()
                    ->color(fn (array $record): string => $record['kind_color']),
                TextColumn::make('size')
                    ->label('الحجم'),
            ])
            ->recordActions([
                Action::make('download')
                    ->label('تنزيل')
                    ->icon(Heroicon::OutlinedArrowDownTray)
                    ->color('gray')
                    ->url(fn (array $record): string => route('filament.admin.backups.download', ['name' => $record['name']])),
                Action::make('restore')
                    ->label('استعادة')
                    ->icon(Heroicon::OutlinedArrowUturnLeft)
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading(fn (array $record): string => "استعادة نسخة {$record['taken_at']}؟")
                    ->modalDescription('تعود بيانات النظام كلها إلى ما كانت عليه عند أخذ هذه النسخة، ويزول كل ما سُجّل بعدها. يأخذ النظام أولاً نسخة من الوضع الحالي لتتمكن من التراجع.')
                    ->modalSubmitActionLabel('نعم، استعد هذه النسخة')
                    ->action(function (array $record, RestoreBackup $restoreBackup): void {
                        Attempt::confirmed(fn () => $restoreBackup->handle(['name' => $record['name']]));

                        Notification::make()
                            ->title('تمت الاستعادة')
                            ->body('الوضع السابق محفوظ في نسخة «قبل استعادة» إن أردت التراجع.')
                            ->success()
                            ->send();

                        // Every screen must be read again from the restored data.
                        $this->redirect(Filament::getUrl());
                    }),
                Action::make('delete')
                    ->label('حذف')
                    ->icon(Heroicon::OutlinedTrash)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading(fn (array $record): string => "حذف نسخة {$record['taken_at']}؟")
                    ->modalDescription('يُحذف ملف النسخة من مجلد النسخ الاحتياطية ولا يمكن استرجاعه.')
                    ->modalSubmitActionLabel('نعم، احذفها')
                    ->action(function (array $record, DeleteBackup $deleteBackup): void {
                        Attempt::confirmed(fn () => $deleteBackup->handle(['name' => $record['name']]));

                        // The list was read (to find this row) before the file was removed.
                        $this->flushCachedTableRecords();
                    })
                    ->successNotificationTitle('حُذفت النسخة'),
            ])
            ->paginated(false)
            ->emptyStateIcon(Heroicon::OutlinedArchiveBox)
            ->emptyStateHeading('لا توجد نسخ احتياطية بعد')
            ->emptyStateDescription('اضغط «نسخة احتياطية الآن» لأخذ أول نسخة.');
    }

    /**
     * The folder is a Windows path inside an Arabic sentence: it is kept
     * left-to-right so its parts stay in order.
     */
    private static function advice(BackupStore $store): HtmlString
    {
        return new HtmlString(
            'يأخذ النظام نسخة تلقائية في أول استعمال كل يوم، ويحتفظ بآخر '.((int) config('rawda.backups.keep')).' منها في المجلد '
            .'<bdi dir="ltr">'.e($store->directory()).'</bdi>'
            .'. لكن تعطّل القرص يُذهب البيانات ونسخها معاً، فنزّل أحدث نسخة من حين لآخر وانقلها إلى فلاشة، أو فعّل النسخة المسائية في Google Drive.',
        );
    }

    /**
     * The set-up of the evening copy: the folder, its address on Drive,
     * the time and the days kept. An empty folder turns it off.
     */
    private function driveSettingsAction(): Action
    {
        return Action::make('driveSettings')
            ->label('إعداد Google Drive')
            ->icon(Heroicon::OutlinedCog6Tooth)
            ->color('gray')
            ->visible(fn (DemoMode $demo): bool => ! $demo->isOn())
            ->modalHeading('النسخة المسائية في Google Drive')
            ->modalDescription('يجب أن يكون برنامج Google Drive (Google Drive for desktop) مثبّتاً على هذا الحاسوب ومسجَّل الدخول. يضع النظام النسخة في مجلد من مجلداته، والبرنامج يرفعها إلى Drive متى اتصل الحاسوب بالإنترنت.')
            ->modalSubmitActionLabel('حفظ')
            ->modalWidth('xl')
            ->fillForm(fn (DriveCopySetup $setup): array => [
                'folder' => $setup->settings()->folder,
                'link' => $setup->settings()->link,
                'time' => $setup->settings()->time,
                'keep_days' => $setup->settings()->keepDays,
            ])
            ->schema([
                TextInput::make('folder')
                    ->label('مجلد Google Drive على هذا الحاسوب')
                    ->placeholder(DriveCopyPresenter::EXAMPLE_FOLDER)
                    ->extraInputAttributes(['dir' => 'ltr'])
                    ->helperText('افتح المجلد في مستكشف الملفات، وانسخ مكانه من شريط العنوان والصقه هنا. اتركه فارغاً لإيقاف النسخة المسائية.'),
                TextInput::make('link')
                    ->label('رابط المجلد على Google Drive (اختياري)')
                    ->placeholder('https://drive.google.com/drive/folders/…')
                    ->extraInputAttributes(['dir' => 'ltr'])
                    ->helperText('انسخه من المتصفح وأنت داخل المجلد على drive.google.com، لتفتحه من هنا وترى أن النسخ وصلت.'),
                TimePicker::make('time')
                    ->label('الوقت')
                    ->seconds(false)
                    ->markAsRequired()
                    ->helperText('توضع نسخة اليوم بعد هذا الوقت، ما دامت صفحة من صفحات النظام مفتوحة. إن كان الحاسوب مطفأً وقتها وُضعت عند أول استعمال بعده.'),
                TextInput::make('keep_days')
                    ->label('مدة الاحتفاظ بالنسخ (بالأيام)')
                    ->inputMode('numeric')
                    ->markAsRequired()
                    ->helperText('تُحذف النسخ الأقدم من المجلد (فتذهب إلى سلّة Drive، وهي تُفرَّغ وحدها بعد 30 يوماً) ومن هذا الحاسوب.'),
            ])
            ->action(function (array $data, Action $action, SaveDriveCopySettings $saveDriveCopySettings): void {
                $settings = Attempt::action($action, fn (): DriveCopySettings => $saveDriveCopySettings->handle($data));

                Notification::make()
                    ->title($settings->isOn() ? 'فُعّلت النسخة المسائية في Google Drive' : 'أُوقفت النسخة المسائية في Google Drive')
                    ->body($settings->isOn() ? "توضع نسخة كل يوم بعد الساعة {$settings->time}. إن لم توضع أي نسخة بعد، توضع الأولى خلال دقائق." : null)
                    ->success()
                    ->send();
            });
    }

    private function driveCopyNowAction(): Action
    {
        return Action::make('driveCopyNow')
            ->label('ضع نسخة في Google Drive الآن')
            ->icon(Heroicon::OutlinedCloudArrowUp)
            ->visible(fn (DriveCopySetup $setup, DemoMode $demo): bool => ! $demo->isOn() && $setup->settings()->isOn())
            ->action(function (CopyBackupToDrive $copyBackupToDrive): void {
                $result = Attempt::button(fn (): DriveCopyResult => $copyBackupToDrive->handle());

                // The list shows the evening backup just taken, and no longer the ones removed.
                $this->flushCachedTableRecords();

                if ($result === null) {
                    return;
                }

                Notification::make()
                    ->title('وُضعت النسخة في مجلد Google Drive')
                    ->body(FileSize::arabic($result->backup->bytes).' — يرفعها برنامج Google Drive متى اتصل الحاسوب بالإنترنت.'
                        .($result->removed === [] ? '' : ' وحُذفت من المجلد '.ArabicNoun::backupCopy()->count(count($result->removed)).' أقدم من مدة الاحتفاظ.'))
                    ->success()
                    ->send();

                if ($result->notRemoved !== []) {
                    Notification::make()
                        ->title('احذف النسخ القديمة من مجلد Google Drive')
                        ->body('تعذّر حذف: '.implode('، ', $result->notRemoved).'. احذفها بيدك.')
                        ->warning()
                        ->persistent()
                        ->send();
                }
            });
    }

    private function openDriveAction(): Action
    {
        return Action::make('openDrive')
            ->label('فتح المجلد على Google Drive')
            ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
            ->color('gray')
            ->visible(fn (DriveCopySetup $setup, DemoMode $demo): bool => ! $demo->isOn() && $setup->settings()->isOn() && $setup->settings()->link !== null)
            ->url(fn (DriveCopySetup $setup): ?string => $setup->settings()->link)
            ->openUrlInNewTab();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('create')
                ->label('نسخة احتياطية الآن')
                ->icon(Heroicon::OutlinedArchiveBoxArrowDown)
                ->action(function (CreateBackup $createBackup): void {
                    $backup = Attempt::button(fn (): Backup => $createBackup->handle());

                    if ($backup !== null) {
                        Notification::make()
                            ->title('أُخذت النسخة الاحتياطية')
                            ->body(FileSize::arabic($backup->bytes).' — نزّلها وانقلها إلى فلاشة.')
                            ->success()
                            ->send();
                    }
                }),
            Action::make('import')
                ->label('إضافة نسخة من ملف')
                ->icon(Heroicon::OutlinedArrowUpTray)
                ->color('gray')
                ->modalHeading('إضافة نسخة احتياطية من ملف')
                ->modalDescription('اختر ملف نسخة أخذها هذا النظام (‎.zip)، من فلاشة مثلاً. يُفحص الملف ثم يُضاف إلى القائمة، ولا يُستعاد شيء حتى تضغط «استعادة».')
                ->modalSubmitActionLabel('إضافة إلى القائمة')
                ->schema([
                    FileToCheck::make('backup', BackupUploadRequest::MAX_KILOBYTES)
                        ->label('ملف النسخة'),
                ])
                ->action(fn (array $data, Action $action, ImportBackup $importBackup) => Attempt::action(
                    $action,
                    fn (): Backup => $importBackup->handle($data),
                ))
                ->successNotificationTitle('أُضيفت النسخة إلى القائمة'),
        ];
    }
}
