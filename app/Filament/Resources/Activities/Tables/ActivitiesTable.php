<?php

namespace App\Filament\Resources\Activities\Tables;

use App\Actions\Activities\DeleteActivity;
use App\Enums\ActivityAudience;
use App\Filament\Support\Filters\DateRangeFilter;
use App\Filament\Support\Options;
use App\Models\Activity;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ActivitiesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('activity_date')
                    ->label('التاريخ')
                    ->date()
                    ->sortable(),
                TextColumn::make('title')
                    ->label('النشاط')
                    ->searchable()
                    ->weight('medium')
                    ->wrap(),
                TextColumn::make('audience')
                    ->label('لمن')
                    ->state(fn (Activity $record): string => self::audience($record))
                    ->badge()
                    ->color(fn (Activity $record): string => $record->circle_id === null ? 'primary' : 'gray'),
                TextColumn::make('attendances_count')
                    ->label('الحاضرون')
                    ->alignCenter()
                    ->sortable(),
                TextColumn::make('points')
                    ->label('نقاط الحضور')
                    ->alignCenter()
                    ->sortable(),
                TextColumn::make('notes')
                    ->label('ملاحظات')
                    ->wrap()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('audience')
                    ->label('لمن')
                    ->options(ActivityAudience::options())
                    ->query(fn (Builder $query, array $data): Builder => match ($data['value'] ?? null) {
                        ActivityAudience::Circle->value => $query->whereNotNull('circle_id'),
                        ActivityAudience::Mosque->value => $query->whereNull('circle_id'),
                        default => $query,
                    }),
                SelectFilter::make('course')
                    ->label('الدورة')
                    ->options(fn (): array => Options::courses())
                    ->query(fn (Builder $query, array $data): Builder => $query->when(
                        $data['value'] ?? null,
                        fn (Builder $query, mixed $courseId) => $query->whereHas(
                            'circle',
                            fn (Builder $query) => $query->where('course_id', $courseId),
                        ),
                    )),
                DateRangeFilter::make('activity_date', 'التاريخ'),
            ])
            ->recordActions([
                EditAction::make()->label('فتح'),
                DeleteAction::make()
                    ->modalHeading('حذف النشاط')
                    ->modalDescription(fn (Activity $record): string => self::deletionWarning($record))
                    ->using(function (Activity $record, DeleteActivity $deleteActivity): bool {
                        $deleteActivity->handle($record);

                        return true;
                    }),
            ])
            ->defaultSort('activity_date', 'desc')
            ->emptyStateHeading('لا توجد نشاطات بعد')
            ->emptyStateDescription('سجّل النشاط بعد إقامته، وضع علامة عند من حضره.');
    }

    /**
     * Deleting an activity takes the points it gave with it: the admin is
     * told before confirming.
     */
    public static function deletionWarning(Activity $activity): string
    {
        $attendees = $activity->attendances()->count();

        if ($attendees === 0) {
            return 'لم يُسجَّل حضور أحد لهذا النشاط.';
        }

        return $activity->points > 0
            ? "يُحذف معه حضور من حضروه (عددهم {$attendees}) والنقاط التي نالوها منه."
            : "يُحذف معه حضور من حضروه (عددهم {$attendees}).";
    }

    private static function audience(Activity $activity): string
    {
        $circle = $activity->circle;

        return $circle === null
            ? ActivityAudience::Mosque->label()
            : "{$circle->name} ({$circle->course->name})";
    }
}
