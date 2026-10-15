<?php

namespace App\Filament\Support\Enrollments;

use App\Actions\Enrollments\DeleteEnrollment;
use App\Actions\Enrollments\TransferStudent;
use App\Actions\Enrollments\UpdateEnrollmentDates;
use App\Filament\Support\Attempt;
use App\Filament\Support\Options;
use App\Models\Enrollment;
use App\Support\Dates;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Support\Icons\Heroicon;

/**
 * The buttons shown on every row of an enrollments table. They are the same
 * whether the table is opened from the course or from the student's file.
 */
final class EnrollmentActions
{
    /**
     * Moves the student to another circle of the same course. The date has
     * no default on purpose: the admin must enter the day of the move.
     */
    public static function transfer(): Action
    {
        return Action::make('transfer')
            ->label('نقل')
            ->icon(Heroicon::OutlinedArrowsRightLeft)
            ->color('gray')
            ->visible(fn (Enrollment $record): bool => ! $record->hasLeft())
            ->modalHeading(fn (Enrollment $record): string => "نقل {$record->student->full_name} إلى حلقة أخرى")
            ->modalDescription('كل ما سجّله الطالب في الدورة (حضور، تسميع، نقاط) ينتقل معه إلى الحلقة الجديدة.')
            ->modalSubmitActionLabel('نقل الطالب')
            ->schema([
                Select::make('to_circle_id')
                    ->label('الحلقة الجديدة')
                    ->options(fn (Enrollment $record): array => collect(Options::circles($record->course_id))
                        ->except($record->circle_id)
                        ->all())
                    ->markAsRequired(),
                DatePicker::make('transferred_on')
                    ->label('تاريخ النقل')
                    ->maxDate(fn () => Dates::today())
                    ->markAsRequired(),
                TextInput::make('notes')
                    ->label('ملاحظات'),
            ])
            ->action(fn (Enrollment $record, array $data, Action $action, TransferStudent $transferStudent) => Attempt::action(
                $action,
                fn () => $transferStudent->handle($record, $data),
            ))
            ->successNotificationTitle('تم نقل الطالب');
    }

    /**
     * Corrects the enrollment date, and records (or clears) the day the
     * student left the course.
     */
    public static function editDates(): Action
    {
        return Action::make('dates')
            ->label('التواريخ')
            ->icon(Heroicon::OutlinedCalendarDays)
            ->color('gray')
            ->modalHeading(fn (Enrollment $record): string => "تواريخ {$record->student->full_name} في الدورة")
            ->modalSubmitActionLabel('حفظ')
            ->fillForm(fn (Enrollment $record): array => [
                'enrolled_on' => $record->enrolled_on->toDateString(),
                'left_on' => $record->left_on?->toDateString(),
            ])
            ->schema([
                DatePicker::make('enrolled_on')
                    ->label('تاريخ التسجيل')
                    ->maxDate(fn () => Dates::today())
                    ->markAsRequired(),
                DatePicker::make('left_on')
                    ->label('تاريخ الخروج من الدورة')
                    ->helperText('اتركه فارغاً ما دام الطالب في الدورة.')
                    ->maxDate(fn () => Dates::today()),
            ])
            ->action(fn (Enrollment $record, array $data, Action $action, UpdateEnrollmentDates $updateEnrollmentDates) => Attempt::action(
                $action,
                fn () => $updateEnrollmentDates->handle($record, $data),
            ))
            ->successNotificationTitle('تم حفظ التواريخ');
    }

    /**
     * Opens the printable attendance card of the student in a new tab.
     */
    public static function printCard(): Action
    {
        return Action::make('card')
            ->label('البطاقة')
            ->icon(Heroicon::OutlinedQrCode)
            ->color('gray')
            ->visible(fn (Enrollment $record): bool => ! $record->hasLeft())
            ->url(fn (Enrollment $record): string => route('filament.admin.attendance-cards.print', [
                'course_id' => $record->course_id,
                'enrollment_ids' => [$record->getKey()],
            ]))
            ->openUrlInNewTab();
    }

    /**
     * Removes an enrollment made by mistake (one with no records at all).
     */
    public static function delete(): DeleteAction
    {
        return DeleteAction::make()
            ->label('حذف التسجيل')
            ->modalHeading(fn (Enrollment $record): string => "حذف تسجيل {$record->student->full_name}")
            ->modalDescription('يُحذف فقط التسجيل الذي ليس له أي سجل. إذا كان للطالب حضور أو تسميع أو نقاط فسجّل تاريخ خروجه من الدورة بدلاً من الحذف.')
            ->using(fn (Enrollment $record, DeleteEnrollment $deleteEnrollment): bool => Attempt::confirmed(function () use ($record, $deleteEnrollment): bool {
                $deleteEnrollment->handle($record);

                return true;
            }));
    }
}
