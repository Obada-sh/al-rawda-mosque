<?php

namespace App\Filament\Resources\Courses\Tables;

use App\Enums\CourseStatus;
use App\Filament\Support\Cards\CardPrintActions;
use App\Filament\Support\Presenters\CoursePresenter;
use App\Models\Course;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class CoursesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('الدورة')
                    ->searchable()
                    ->sortable()
                    ->weight('medium'),
                TextColumn::make('status')
                    ->label('الحالة')
                    ->badge()
                    ->formatStateUsing(fn (CourseStatus $state): string => $state->label())
                    ->color(fn (CourseStatus $state): string => CoursePresenter::statusColor($state)),
                TextColumn::make('start_date')
                    ->label('البداية')
                    ->date()
                    ->sortable(),
                TextColumn::make('end_date')
                    ->label('النهاية')
                    ->date()
                    ->placeholder('غير محددة'),
                TextColumn::make('days')
                    ->label('أيام الدوام')
                    ->state(fn (Course $record): string => CoursePresenter::days($record))
                    ->wrap(),
                TextColumn::make('circles_count')
                    ->label('الحلقات')
                    ->counts('circles')
                    ->alignCenter(),
                TextColumn::make('enrollments_count')
                    ->label('الطلاب')
                    ->counts(['enrollments' => fn (Builder $query) => $query->whereNull('left_on')])
                    ->alignCenter(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('الحالة')
                    ->options(CourseStatus::options()),
            ])
            ->recordActions([
                EditAction::make()->label('فتح'),
                ActionGroup::make([
                    Action::make('cards')
                        ->label('بطاقات الطلاب')
                        ->icon(Heroicon::OutlinedPrinter)
                        ->url(fn (Course $record): string => CardPrintActions::url($record->getKey()))
                        ->openUrlInNewTab(),
                    Action::make('teacherCards')
                        ->label('بطاقات الأساتذة')
                        ->icon(Heroicon::OutlinedIdentification)
                        ->url(fn (Course $record): string => CardPrintActions::teachersUrl($record->getKey()))
                        ->openUrlInNewTab(),
                ])
                    ->label('البطاقات')
                    ->icon(Heroicon::OutlinedPrinter)
                    ->color('gray')
                    ->button(),
            ])
            ->defaultSort('start_date', 'desc');
    }
}
