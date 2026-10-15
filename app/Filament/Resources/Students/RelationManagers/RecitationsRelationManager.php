<?php

namespace App\Filament\Resources\Students\RelationManagers;

use App\Filament\Support\Filters\DateRangeFilter;
use App\Filament\Support\Options;
use App\Models\Recitation;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Everything the student recited, in every course: which pages, in which
 * course, to which teacher and when. Recitations are entered and corrected
 * from the recitations screen.
 */
class RecitationsRelationManager extends RelationManager
{
    protected static string $relationship = 'recitations';

    protected static ?string $title = 'التسميع';

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['enrollment.course', 'teacher', 'pointTransaction']))
            ->columns([
                TextColumn::make('recited_on')
                    ->label('التاريخ')
                    ->date()
                    ->sortable(),
                TextColumn::make('enrollment.course.name')
                    ->label('الدورة'),
                TextColumn::make('teacher.full_name')
                    ->label('الأستاذ'),
                TextColumn::make('pages')
                    ->label('الصفحات')
                    ->state(fn (Recitation $record): string => $record->from_page === $record->to_page
                        ? (string) $record->from_page
                        : "{$record->from_page} – {$record->to_page}")
                    ->alignCenter(),
                TextColumn::make('pages_count')
                    ->label('العدد')
                    ->alignCenter()
                    ->summarize(Sum::make()->label('المجموع')),
                TextColumn::make('new_pages_count')
                    ->label('جديدة')
                    ->alignCenter()
                    ->weight('bold')
                    ->summarize(Sum::make()->label('المجموع')),
                TextColumn::make('pointTransaction.points')
                    ->label('النقاط')
                    ->placeholder('0')
                    ->alignCenter(),
                TextColumn::make('notes')
                    ->label('ملاحظات')
                    ->wrap()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('course')
                    ->label('الدورة')
                    ->options(fn (): array => Options::courses())
                    ->query(fn (Builder $query, array $data): Builder => $query->when(
                        $data['value'] ?? null,
                        fn (Builder $query, mixed $courseId) => $query->where('enrollments.course_id', $courseId),
                    )),
                DateRangeFilter::make('recited_on', 'التاريخ'),
            ])
            ->defaultSort('recited_on', 'desc')
            ->emptyStateHeading('لم يسمّع الطالب شيئاً بعد');
    }
}
