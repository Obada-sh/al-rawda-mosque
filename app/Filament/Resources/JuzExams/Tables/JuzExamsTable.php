<?php

namespace App\Filament\Resources\JuzExams\Tables;

use App\Actions\Exams\DeleteJuzExam;
use App\Actions\Exams\RecordJuzExamResult;
use App\Enums\ExamGrade;
use App\Enums\ExamOrigin;
use App\Enums\ExamStatus;
use App\Filament\Support\Attempt;
use App\Filament\Support\Filters\CourseFilter;
use App\Filament\Support\Options;
use App\Filament\Support\Presenters\ExamPresenter;
use App\Models\JuzExam;
use App\Support\Dates;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class JuzExamsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('student.full_name')
                    ->label('الطالب')
                    ->searchable()
                    ->weight('medium'),
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
                    ->date()
                    ->sortable(),
                TextColumn::make('enrollment.course.name')
                    ->label('الدورة')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('exam_date')
                    ->label('تاريخ السبر')
                    ->date()
                    ->placeholder('—')
                    ->sortable(),
                TextColumn::make('examiner.full_name')
                    ->label('الأستاذ')
                    ->placeholder('—'),
                TextColumn::make('grade')
                    ->label('التقييم')
                    ->badge()
                    ->formatStateUsing(fn (?ExamGrade $state): ?string => $state?->label())
                    ->color(fn (?ExamGrade $state): string => ExamPresenter::gradeColor($state))
                    ->placeholder('—'),
                TextColumn::make('origin')
                    ->label('فُتح')
                    ->formatStateUsing(fn (ExamOrigin $state): string => $state->label())
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('notes')
                    ->label('ملاحظات')
                    ->wrap()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                CourseFilter::make(),
                SelectFilter::make('grade')
                    ->label('التقييم')
                    ->options(ExamGrade::options()),
            ])
            ->recordActions([
                self::resultAction(),
                DeleteAction::make()
                    ->modalHeading(fn (JuzExam $record): string => "حذف سبر الجزء {$record->juz_no} للطالب {$record->student->full_name}")
                    ->using(function (JuzExam $record, DeleteJuzExam $deleteJuzExam): bool {
                        $deleteJuzExam->handle($record);

                        return true;
                    }),
            ])
            ->defaultSort('triggered_on', 'desc')
            ->emptyStateHeading('لا يوجد سبر');
    }

    /**
     * Enters the result of an exam, or corrects a result entered earlier.
     */
    private static function resultAction(): Action
    {
        return Action::make('result')
            ->label(fn (JuzExam $record): string => $record->isPending() ? 'تسجيل النتيجة' : 'تعديل النتيجة')
            ->icon(Heroicon::OutlinedPencilSquare)
            ->color(fn (JuzExam $record): string => $record->isPending() ? 'primary' : 'gray')
            ->modalHeading(fn (JuzExam $record): string => "سبر الجزء {$record->juz_no} — {$record->student->full_name}")
            ->modalSubmitActionLabel('حفظ النتيجة')
            ->fillForm(fn (JuzExam $record): array => [
                'exam_date' => $record->exam_date?->toDateString() ?? Dates::today()->toDateString(),
                'examiner_teacher_id' => $record->examiner_teacher_id,
                'grade' => $record->grade?->value,
                'notes' => $record->notes,
            ])
            ->schema([
                DatePicker::make('exam_date')
                    ->label('تاريخ السبر')
                    ->maxDate(fn () => Dates::today())
                    ->markAsRequired(),
                Select::make('examiner_teacher_id')
                    ->label('الأستاذ')
                    ->options(fn (JuzExam $record): array => Options::teachers($record->examiner_teacher_id))
                    ->searchable()
                    ->markAsRequired(),
                Select::make('grade')
                    ->label('التقييم')
                    ->options(ExamGrade::options())
                    ->markAsRequired(),
                Textarea::make('notes')
                    ->label('ملاحظات')
                    ->rows(2),
            ])
            ->action(fn (JuzExam $record, array $data, Action $action, RecordJuzExamResult $recordJuzExamResult): JuzExam => Attempt::action(
                $action,
                fn (): JuzExam => $recordJuzExamResult->handle($record, $data),
            ))
            ->successNotificationTitle('تم حفظ نتيجة السبر');
    }
}
