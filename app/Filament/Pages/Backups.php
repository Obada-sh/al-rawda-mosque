<?php

namespace App\Filament\Pages;

use App\Actions\Backups\CreateBackup;
use App\Actions\Backups\DeleteBackup;
use App\Actions\Backups\ImportBackup;
use App\Actions\Backups\RestoreBackup;
use App\Filament\Enums\PanelGroup;
use App\Filament\Support\Attempt;
use App\Filament\Support\Fields\FileToCheck;
use App\Filament\Support\Presenters\BackupPresenter;
use App\Http\Requests\Backups\BackupUploadRequest;
use App\Services\Backup\Backup;
use App\Services\Backup\BackupStore;
use App\Support\FileSize;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
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
 * bring one back from outside, restore one and delete one.
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

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                Callout::make('احفظ نسخة خارج هذا الحاسوب')
                    ->description(fn (BackupStore $store): HtmlString => self::advice($store))
                    ->info(),
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
            .'. لكن تعطّل القرص يُذهب البيانات ونسخها معاً، فنزّل أحدث نسخة من حين لآخر وانقلها إلى فلاشة.',
        );
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
