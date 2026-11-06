<?php

namespace App\Filament\English\Resources\EnglishCourses\Tables;

use App\Enums\CourseStatus;
use App\Filament\English\Support\EnglishOptions;
use App\Filament\English\Support\EnglishPresenter;
use App\Filament\Support\Presenters\CoursePresenter;
use App\Models\English\EnglishCourse;
use App\Support\Money;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class EnglishCoursesTable
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
                TextColumn::make('level.name')
                    ->label('المستوى')
                    ->badge()
                    ->color('primary'),
                TextColumn::make('price')
                    ->label('السعر')
                    ->formatStateUsing(fn (int $state): string => Money::pounds($state))
                    ->sortable(),
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
                    ->state(fn (EnglishCourse $record): string => EnglishPresenter::days($record))
                    ->wrap(),
                TextColumn::make('halls_count')
                    ->label('القاعات')
                    ->counts('halls')
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
                SelectFilter::make('english_level_id')
                    ->label('المستوى')
                    ->options(fn (): array => EnglishOptions::levels()),
            ])
            ->recordActions([
                EditAction::make()->label('فتح'),
            ])
            ->defaultSort('start_date', 'desc');
    }
}
