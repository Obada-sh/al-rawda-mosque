<?php

namespace App\Filament\Resources\Students\RelationManagers;

use App\Enums\PointSource;
use App\Filament\Support\Filters\DateRangeFilter;
use App\Filament\Support\Options;
use App\Filament\Support\Presenters\PointsPresenter;
use App\Models\PointTransaction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * The student's points in every course, each with the course it was earned
 * in. Manual points are added and corrected from the points screen.
 */
class PointTransactionsRelationManager extends RelationManager
{
    protected static string $relationship = 'pointTransactions';

    protected static ?string $title = 'النقاط';

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['enrollment.course']))
            ->columns([
                TextColumn::make('occurred_on')
                    ->label('التاريخ')
                    ->date()
                    ->sortable(),
                TextColumn::make('enrollment.course.name')
                    ->label('الدورة'),
                TextColumn::make('source')
                    ->label('المصدر')
                    ->badge()
                    ->formatStateUsing(fn (PointSource $state): string => $state->label())
                    ->color(fn (PointSource $state): string => PointsPresenter::sourceColor($state)),
                TextColumn::make('points')
                    ->label('النقاط')
                    ->alignCenter()
                    ->weight('bold')
                    ->color(fn (PointTransaction $record): string => PointsPresenter::amountColor($record->points))
                    ->summarize(Sum::make()->label('المجموع')),
                TextColumn::make('reason')
                    ->label('السبب')
                    ->placeholder('—')
                    ->wrap(),
            ])
            ->filters([
                SelectFilter::make('course')
                    ->label('الدورة')
                    ->options(fn (): array => Options::courses())
                    ->query(fn (Builder $query, array $data): Builder => $query->when(
                        $data['value'] ?? null,
                        fn (Builder $query, mixed $courseId) => $query->where('enrollments.course_id', $courseId),
                    )),
                SelectFilter::make('source')
                    ->label('المصدر')
                    ->options(PointSource::options()),
                DateRangeFilter::make('occurred_on', 'التاريخ'),
            ])
            ->defaultSort('occurred_on', 'desc')
            ->emptyStateHeading('لا توجد نقاط بعد');
    }
}
