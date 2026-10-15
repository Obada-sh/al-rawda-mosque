<?php

namespace App\Filament\Resources\Students\RelationManagers;

use App\Enums\ExamGrade;
use App\Enums\ExamStatus;
use App\Filament\Support\Presenters\ExamPresenter;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * The student's juz exams. Results are entered from the exams screen.
 */
class JuzExamsRelationManager extends RelationManager
{
    protected static string $relationship = 'juzExams';

    protected static ?string $title = 'السبر';

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['enrollment.course', 'examiner']))
            ->columns([
                TextColumn::make('juz_no')
                    ->label('الجزء')
                    ->sortable()
                    ->alignCenter(),
                TextColumn::make('status')
                    ->label('الحالة')
                    ->badge()
                    ->formatStateUsing(fn (ExamStatus $state): string => $state->label())
                    ->color(fn (ExamStatus $state): string => ExamPresenter::statusColor($state)),
                TextColumn::make('triggered_on')
                    ->label('تاريخ الاستحقاق')
                    ->date(),
                TextColumn::make('enrollment.course.name')
                    ->label('الدورة')
                    ->placeholder('—'),
                TextColumn::make('exam_date')
                    ->label('تاريخ السبر')
                    ->date()
                    ->placeholder('—'),
                TextColumn::make('examiner.full_name')
                    ->label('الأستاذ')
                    ->placeholder('—'),
                TextColumn::make('grade')
                    ->label('التقييم')
                    ->badge()
                    ->formatStateUsing(fn (?ExamGrade $state): ?string => $state?->label())
                    ->color(fn (?ExamGrade $state): string => ExamPresenter::gradeColor($state))
                    ->placeholder('—'),
            ])
            ->defaultSort('juz_no')
            ->emptyStateHeading('لا يوجد سبر بعد')
            ->paginated(false);
    }
}
