<?php

namespace App\Filament\Support\Tables;

use App\Filament\Support\Filters\DateRangeFilter;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * The transfers log: who moved from which circle to which, and when.
 *
 * It is the same table on the course page and in the student's file. It is
 * a record for reading only: a student's work always belongs to the circle
 * they are in now.
 */
final class TransfersLogTable
{
    /**
     * @param  bool  $ofOneStudent  in a student's file the student is known, and the course is shown instead
     */
    public static function configure(Table $table, bool $ofOneStudent = false): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with([
                'enrollment.student',
                'enrollment.course',
                'fromCircle',
                'toCircle',
            ]))
            ->columns([
                TextColumn::make('transferred_on')
                    ->label('تاريخ النقل')
                    ->date()
                    ->sortable(),
                TextColumn::make('enrollment.student.full_name')
                    ->label('الطالب')
                    ->weight('medium')
                    ->hidden($ofOneStudent),
                TextColumn::make('enrollment.course.name')
                    ->label('الدورة')
                    ->weight('medium')
                    ->visible($ofOneStudent),
                TextColumn::make('fromCircle.name')
                    ->label('من حلقة'),
                TextColumn::make('toCircle.name')
                    ->label('إلى حلقة'),
                TextColumn::make('notes')
                    ->label('ملاحظات')
                    ->placeholder('—')
                    ->wrap(),
            ])
            ->filters([
                DateRangeFilter::make('transferred_on', 'تاريخ النقل'),
            ])
            ->defaultSort('transferred_on', 'desc')
            ->emptyStateHeading($ofOneStudent ? 'لم يُنقل الطالب بين الحلقات' : 'لم يُنقل أي طالب بين الحلقات');
    }
}
