<?php

namespace App\Filament\Resources\Recitations\Tables;

use App\Actions\Recitations\DeleteRecitation;
use App\Actions\Recitations\UpdateRecitation;
use App\Filament\Support\Attempt;
use App\Filament\Support\Filters\CourseFilter;
use App\Filament\Support\Filters\DateRangeFilter;
use App\Models\Recitation;
use App\Models\Teacher;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class RecitationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('recited_on')
                    ->label('التاريخ')
                    ->date()
                    ->sortable(),
                TextColumn::make('enrollment.student.full_name')
                    ->label('الطالب')
                    ->searchable()
                    ->weight('medium'),
                TextColumn::make('enrollment.circle.name')
                    ->label('الحلقة'),
                TextColumn::make('enrollment.course.name')
                    ->label('الدورة')
                    ->toggleable(),
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
                    ->tooltip('صفحات يسمّعها الطالب لأول مرة')
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
                CourseFilter::make(),
                SelectFilter::make('teacher_id')
                    ->label('الأستاذ')
                    ->options(fn (): array => Teacher::query()->orderBy('full_name')->pluck('full_name', 'id')->all())
                    ->searchable(),
                DateRangeFilter::make('recited_on', 'التاريخ'),
            ])
            ->recordActions([
                EditAction::make()
                    ->modalHeading('تعديل التسميع')
                    ->using(fn (Recitation $record, array $data, Action $action, UpdateRecitation $updateRecitation): Recitation => Attempt::action(
                        $action,
                        fn (): Recitation => $updateRecitation->handle($record, $data),
                    )),
                DeleteAction::make()
                    ->modalHeading('حذف التسميع')
                    ->modalDescription('تُحذف نقاط هذا التسميع، ويُعاد حساب محفوظ الطالب ونقاطه وسبره.')
                    ->using(function (Recitation $record, DeleteRecitation $deleteRecitation): bool {
                        $deleteRecitation->handle($record);

                        return true;
                    }),
            ])
            ->defaultSort('recited_on', 'desc')
            ->emptyStateHeading('لا يوجد تسميع مسجَّل');
    }
}
