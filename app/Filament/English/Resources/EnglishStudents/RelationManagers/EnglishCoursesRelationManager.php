<?php

namespace App\Filament\English\Resources\EnglishStudents\RelationManagers;

use App\Filament\English\Resources\EnglishCourses\EnglishCourseResource;
use App\Filament\English\Support\EnglishEnrollmentActions;
use App\Filament\English\Support\EnglishMoneyColumns;
use App\Models\English\EnglishEnrollment;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * The student's English courses: the hall, the dates, and where the
 * student stands with the price of each.
 */
class EnglishCoursesRelationManager extends RelationManager
{
    protected static string $relationship = 'englishEnrollments';

    protected static ?string $title = 'الدورات';

    protected static ?string $modelLabel = 'دورة';

    /** The student's file is where a payment is recorded too. */
    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => EnglishMoneyColumns::withMoney($query)
                ->with(['hall', 'student'])
                ->withCount('attendances'))
            ->columns([
                TextColumn::make('course.name')
                    ->label('الدورة')
                    ->weight('medium'),
                TextColumn::make('hall.name')
                    ->label('القاعة'),
                TextColumn::make('enrolled_on')
                    ->label('تاريخ التسجيل')
                    ->date()
                    ->sortable(),
                TextColumn::make('left_on')
                    ->label('تاريخ الخروج')
                    ->date()
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('attendances_count')
                    ->label('أيام الحضور')
                    ->alignCenter(),
                ...EnglishMoneyColumns::make(),
            ])
            ->recordActions([
                Action::make('course')
                    ->label('فتح الدورة')
                    ->icon(Heroicon::OutlinedAcademicCap)
                    ->color('gray')
                    ->url(fn (EnglishEnrollment $record): string => EnglishCourseResource::getUrl('edit', ['record' => $record->english_course_id])),
                EnglishEnrollmentActions::pay(),
                EnglishEnrollmentActions::printCard(),
                ActionGroup::make([
                    EnglishEnrollmentActions::changePrice(),
                    EnglishEnrollmentActions::editDates(),
                ]),
            ])
            ->defaultSort('enrolled_on', 'desc')
            ->emptyStateHeading('لم يُسجَّل الطالب في أي دورة انجليزي بعد')
            ->paginated(false);
    }
}
