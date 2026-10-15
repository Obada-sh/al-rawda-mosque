<?php

namespace App\Filament\Resources\Students\RelationManagers;

use App\Enums\ActivityAudience;
use App\Filament\Support\Filters\DateRangeFilter;
use App\Models\Activity;
use App\Models\ActivityAttendance;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * The activities the student attended, in every course, also those that
 * gave no points. Activities are recorded and corrected from the
 * activities screen.
 */
class ActivitiesRelationManager extends RelationManager
{
    protected static string $relationship = 'activityAttendances';

    protected static ?string $title = 'النشاطات';

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['activity.circle', 'enrollment.course']))
            ->columns([
                TextColumn::make('activity.activity_date')
                    ->label('التاريخ')
                    ->date(),
                TextColumn::make('activity.title')
                    ->label('النشاط')
                    ->weight('medium')
                    ->wrap(),
                TextColumn::make('audience')
                    ->label('لمن')
                    ->state(fn (ActivityAttendance $record): string => $record->activity->circle?->name ?? ActivityAudience::Mosque->label())
                    ->badge()
                    ->color(fn (ActivityAttendance $record): string => $record->activity->circle_id === null ? 'primary' : 'gray'),
                TextColumn::make('enrollment.course.name')
                    ->label('الدورة'),
                TextColumn::make('activity.points')
                    ->label('النقاط')
                    ->alignCenter(),
            ])
            ->filters([
                DateRangeFilter::make('activity_date', 'التاريخ', 'activity'),
            ])
            // Newest first, by the date of the activity (a column of another table).
            ->defaultSort(fn (Builder $query): Builder => $query->orderByDesc(
                Activity::query()->select('activity_date')->whereColumn('activities.id', 'activity_attendances.activity_id'),
            ))
            ->emptyStateHeading('لم يحضر الطالب نشاطاً بعد');
    }
}
