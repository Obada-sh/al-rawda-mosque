<?php

namespace App\Filament\English\Resources\EnglishAttendances;

use App\Actions\English\Attendance\DeleteEnglishAttendance;
use App\Enums\AttendanceMethod;
use App\Filament\English\EnglishPanelGroup;
use App\Filament\English\Resources\EnglishAttendances\Pages\ManageEnglishAttendances;
use App\Filament\English\Support\EnglishOptions;
use App\Filament\Support\Filters\DateRangeFilter;
use App\Models\English\EnglishAttendance;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * The attendance register of the English courses: every attendance, by
 * card or by hand, with the hand-made ones added from here.
 */
class EnglishAttendanceResource extends Resource
{
    protected static ?string $model = EnglishAttendance::class;

    protected static ?string $slug = 'attendance';

    protected static string|UnitEnum|null $navigationGroup = EnglishPanelGroup::Daily;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static ?int $navigationSort = 20;

    protected static ?string $modelLabel = 'حضور';

    protected static ?string $pluralModelLabel = 'سجل الحضور';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['session', 'enrollment.student', 'enrollment.hall', 'enrollment.course']);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('session.session_date')
                    ->label('التاريخ')
                    ->date()
                    ->sortable(),
                TextColumn::make('enrollment.student.full_name')
                    ->label('الطالب')
                    ->searchable()
                    ->weight('medium'),
                TextColumn::make('enrollment.hall.name')
                    ->label('القاعة'),
                TextColumn::make('enrollment.course.name')
                    ->label('الدورة'),
                TextColumn::make('method')
                    ->label('الطريقة')
                    ->badge()
                    ->formatStateUsing(fn (AttendanceMethod $state): string => $state->label())
                    ->color(fn (AttendanceMethod $state): string => $state === AttendanceMethod::Qr ? 'success' : 'gray'),
                TextColumn::make('scanned_at')
                    ->label('وقت المسح')
                    ->time('H:i')
                    ->placeholder('—')
                    ->alignCenter(),
            ])
            ->filters([
                SelectFilter::make('course')
                    ->label('الدورة')
                    ->options(fn (): array => EnglishOptions::courses())
                    ->query(fn (Builder $query, array $data): Builder => $query->when(
                        $data['value'] ?? null,
                        fn (Builder $query, mixed $courseId) => $query->whereHas('session', fn (Builder $query) => $query->where('english_course_id', $courseId)),
                    )),
                DateRangeFilter::make('session_date', 'التاريخ', relationship: 'session'),
                SelectFilter::make('method')
                    ->label('الطريقة')
                    ->options(AttendanceMethod::options()),
            ])
            ->recordActions([
                DeleteAction::make()
                    ->modalHeading(fn (EnglishAttendance $record): string => "حذف حضور {$record->enrollment->student->full_name}")
                    ->using(function (EnglishAttendance $record, DeleteEnglishAttendance $deleteAttendance): bool {
                        $deleteAttendance->handle($record);

                        return true;
                    }),
            ])
            ->defaultSort('id', 'desc')
            ->emptyStateHeading('لا يوجد حضور مسجَّل');
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageEnglishAttendances::route('/'),
        ];
    }
}
