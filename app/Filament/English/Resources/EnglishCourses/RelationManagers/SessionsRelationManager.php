<?php

namespace App\Filament\English\Resources\EnglishCourses\RelationManagers;

use App\Actions\English\Attendance\CancelEnglishSession;
use App\Actions\English\Attendance\OpenEnglishSession;
use App\Actions\English\Attendance\ReopenEnglishSession;
use App\Enums\SessionStatus;
use App\Enums\Weekday;
use App\Filament\Support\Attempt;
use App\Filament\Support\Presenters\CoursePresenter;
use App\Models\English\EnglishCourse;
use App\Models\English\EnglishSession;
use App\Support\Dates;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * The class days of the English course. A session opens by itself on the first
 * attendance of a day; here the admin can open an extra day, or cancel a
 * day that was a holiday so it does not count against anyone.
 */
class SessionsRelationManager extends RelationManager
{
    protected static string $relationship = 'sessions';

    protected static ?string $title = 'الجلسات';

    protected static ?string $modelLabel = 'جلسة';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('session_date')
                    ->label('التاريخ')
                    ->date()
                    ->sortable(),
                TextColumn::make('weekday')
                    ->label('اليوم')
                    ->state(fn (EnglishSession $record): string => Weekday::fromDate($record->session_date)->label()),
                TextColumn::make('status')
                    ->label('الحالة')
                    ->badge()
                    ->formatStateUsing(fn (SessionStatus $state): string => $state->label())
                    ->color(fn (SessionStatus $state): string => CoursePresenter::sessionStatusColor($state)),
                TextColumn::make('attendances_count')
                    ->label('عدد الحاضرين')
                    ->counts('attendances')
                    ->alignCenter(),
            ])
            ->headerActions([
                Action::make('open')
                    ->label('فتح جلسة')
                    ->icon(Heroicon::OutlinedPlus)
                    ->modalHeading('فتح جلسة في يوم خارج أيام الدوام')
                    ->modalDescription('الجلسات في أيام الدوام تُفتح تلقائياً عند أول حضور. استعمل هذا ليوم إضافي، أو لإعادة فتح جلسة ملغاة.')
                    ->modalSubmitActionLabel('فتح الجلسة')
                    ->schema([
                        DatePicker::make('session_date')
                            ->label('تاريخ الجلسة')
                            ->default(fn (): string => Dates::today()->toDateString())
                            ->maxDate(fn () => Dates::today())
                            ->markAsRequired(),
                    ])
                    ->action(fn (array $data, Action $action, OpenEnglishSession $openSession): EnglishSession => Attempt::action(
                        $action,
                        fn (): EnglishSession => $openSession->handle($this->course(), $data),
                    ))
                    ->successNotificationTitle('تم فتح الجلسة'),
            ])
            ->recordActions([
                Action::make('cancel')
                    ->label('إلغاء الجلسة')
                    ->icon(Heroicon::OutlinedNoSymbol)
                    ->color('danger')
                    ->visible(fn (EnglishSession $record): bool => ! $record->isCancelled())
                    ->requiresConfirmation()
                    ->modalDescription('الجلسة الملغاة (عطلة مثلاً) لا تُحسب في نسب الحضور ولا يُقبل فيها مسح البطاقات.')
                    ->action(fn (EnglishSession $record, CancelEnglishSession $cancelSession): EnglishSession => Attempt::confirmed(
                        fn (): EnglishSession => $cancelSession->handle($record),
                    ))
                    ->successNotificationTitle('تم إلغاء الجلسة'),
                Action::make('reopen')
                    ->label('إعادة فتح')
                    ->icon(Heroicon::OutlinedArrowPath)
                    ->color('gray')
                    ->visible(fn (EnglishSession $record): bool => $record->isCancelled())
                    ->action(fn (EnglishSession $record, ReopenEnglishSession $reopenSession): EnglishSession => $reopenSession->handle($record))
                    ->successNotificationTitle('تمت إعادة فتح الجلسة'),
            ])
            ->defaultSort('session_date', 'desc')
            ->emptyStateHeading('لا توجد جلسات بعد')
            ->emptyStateDescription('تُفتح الجلسة تلقائياً عند أول حضور في يوم الدوام.');
    }

    private function course(): EnglishCourse
    {
        /** @var EnglishCourse */
        return $this->getOwnerRecord();
    }
}
