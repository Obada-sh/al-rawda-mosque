<?php

namespace App\Filament\English\Resources\EnglishStudents\RelationManagers;

use App\Enums\English\TestKind;
use App\Filament\English\Resources\EnglishCourses\RelationManagers\TestsRelationManager;
use App\Models\English\EnglishTest;
use App\Models\English\EnglishTestResult;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * The student's marks in every exam and recitation of their English
 * courses, the latest first.
 */
class EnglishResultsRelationManager extends RelationManager
{
    protected static string $relationship = 'englishTestResults';

    protected static ?string $title = 'العلامات';

    protected static ?string $modelLabel = 'علامة';

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['test', 'enrollment.course']))
            ->columns([
                TextColumn::make('test.held_on')
                    ->label('التاريخ')
                    ->date(),
                TextColumn::make('test.title')
                    ->label('الامتحان')
                    ->weight('medium'),
                TextColumn::make('test.kind')
                    ->label('النوع')
                    ->badge()
                    ->formatStateUsing(fn (TestKind $state): string => $state->label())
                    ->color(fn (TestKind $state): string => $state === TestKind::Exam ? 'primary' : 'gray'),
                TextColumn::make('enrollment.course.name')
                    ->label('الدورة'),
                TextColumn::make('result')
                    ->label('العلامة')
                    ->state(fn (EnglishTestResult $record): string => $record->is_absent
                        ? 'غائب'
                        : TestsRelationManager::plain((string) $record->mark).' / '.$record->test->max_mark)
                    ->badge()
                    ->color(fn (EnglishTestResult $record): string => $record->is_absent ? 'danger' : 'gray'),
                TextColumn::make('notes')
                    ->label('ملاحظة')
                    ->placeholder('—'),
            ])
            ->defaultSort(fn (Builder $query): Builder => $query
                ->orderByDesc(EnglishTest::query()->select('held_on')->whereColumn('english_tests.id', 'english_test_results.english_test_id')))
            ->emptyStateHeading('لا توجد علامات بعد');
    }
}
