<?php

namespace App\Filament\Support\Documents;

use App\Filament\Support\Attempt;
use App\Filament\Support\NewWindow;
use App\Filament\Support\Options;
use App\Http\Requests\Documents\CircleSheetRequest;
use App\Http\Requests\Documents\StudentFileRequest;
use App\Models\Circle;
use App\Models\Student;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Support\Icons\Heroicon;
use Livewire\Component;

/**
 * The buttons that open the documents printed on A4 paper: a student's
 * file, and the list of a circle's students or their attendance codes.
 * They only open the page, in a new window; what is printed is decided and
 * checked by PrepareStudentFile and PrepareCircleSheets.
 */
final class DocumentActions
{
    /**
     * A student's file: every course of the student, or one of them.
     */
    public static function studentFileUrl(int|string $studentId, int|string|null $courseId = null): string
    {
        return route('filament.admin.print.student-file', array_filter([
            'student' => $studentId,
            'course_id' => $courseId,
        ], fn (mixed $value): bool => filled($value)));
    }

    /**
     * The list of the students of one circle, or of every circle of a course.
     */
    public static function circleStudentsUrl(int|string|null $circleId = null, int|string|null $courseId = null): string
    {
        return route('filament.admin.print.circle-students', self::circleQuery($circleId, $courseId));
    }

    /**
     * The attendance codes of one circle, or of every circle of a course.
     */
    public static function circleCodesUrl(int|string|null $circleId = null, int|string|null $courseId = null): string
    {
        return route('filament.admin.print.circle-codes', self::circleQuery($circleId, $courseId));
    }

    /**
     * On a student's page and in their row: their file. A student of one
     * course (or none yet) gets it at once; for a student of several, the
     * admin picks one course or all of them.
     */
    public static function studentFile(): Action
    {
        return Action::make('studentFile')
            ->label('ملف A4')
            ->icon(Heroicon::OutlinedDocumentText)
            ->color('gray')
            ->url(fn (Student $record): ?string => self::courseCount($record) <= 1
                ? self::studentFileUrl($record->getKey())
                : null)
            ->openUrlInNewTab()
            ->modalHeading(fn (Student $record): string => "ملف {$record->full_name}")
            ->modalDescription('صفحة A4 تحت شعار الجامع: بيانات الطالب، وكل ما حفظه وسمّعه، وحضوره جلسةً جلسة. تُفتح في نافذة جديدة للطباعة أو للحفظ بصيغة PDF.')
            ->modalSubmitActionLabel('فتح الملف')
            ->modalWidth('lg')
            ->schema(fn (Student $record): array => [
                Select::make('course_id')
                    ->label('الدورة')
                    ->placeholder('كل الدورات')
                    ->options(fn (): array => Options::coursesOfStudent($record->getKey())),
            ])
            ->action(function (Student $record, array $data, Action $action, Component $livewire): void {
                $data = Attempt::action($action, fn (): array => StudentFileRequest::validateData($data, ['student_id' => $record->getKey()]));

                NewWindow::open($livewire, self::studentFileUrl($record->getKey(), $data['course_id'] ?? null));
            });
    }

    /**
     * In a circle's row: the list of its students.
     */
    public static function circleStudents(): Action
    {
        return Action::make('circleStudents')
            ->label('كشف الطلاب')
            ->icon(Heroicon::OutlinedTableCells)
            ->color('gray')
            ->url(fn (Circle $record): string => self::circleStudentsUrl($record->getKey()))
            ->openUrlInNewTab();
    }

    /**
     * In a circle's row: the attendance codes of its students.
     */
    public static function circleCodes(): Action
    {
        return Action::make('circleCodes')
            ->label('رموز الحضور')
            ->icon(Heroicon::OutlinedQrCode)
            ->color('gray')
            ->url(fn (Circle $record): string => self::circleCodesUrl($record->getKey()))
            ->openUrlInNewTab();
    }

    /**
     * Above the circles: the lists of every circle of the chosen course.
     */
    public static function courseStudents(): Action
    {
        return self::wholeCourse('courseStudents', 'كشوف كل الحلقات', Heroicon::OutlinedTableCells)
            ->action(function (array $data, Action $action, Component $livewire): void {
                $data = Attempt::action($action, fn (): array => CircleSheetRequest::validateData($data));

                NewWindow::open($livewire, self::circleStudentsUrl(courseId: $data['course_id']));
            });
    }

    /**
     * Above the circles: the attendance codes of every circle of the chosen course.
     */
    public static function courseCodes(): Action
    {
        return self::wholeCourse('courseCodes', 'رموز كل الحلقات', Heroicon::OutlinedQrCode)
            ->action(function (array $data, Action $action, Component $livewire): void {
                $data = Attempt::action($action, fn (): array => CircleSheetRequest::validateData($data));

                NewWindow::open($livewire, self::circleCodesUrl(courseId: $data['course_id']));
            });
    }

    private static function wholeCourse(string $name, string $label, Heroicon $icon): Action
    {
        return Action::make($name)
            ->label($label)
            ->icon($icon)
            ->color('gray')
            ->modalHeading($label)
            ->modalDescription('كل حلقة من الدورة تبدأ بصفحة جديدة. الحلقات التي ليس فيها طلاب لا تُطبع.')
            ->modalSubmitActionLabel('فتح صفحة الطباعة')
            ->modalWidth('lg')
            ->schema([
                Select::make('course_id')
                    ->label('الدورة')
                    ->options(fn (): array => Options::courses())
                    ->default(fn (): ?int => Options::soleActiveCourseId())
                    ->markAsRequired(),
            ]);
    }

    /**
     * How many courses the student is or was in (one enrollment per course).
     */
    private static function courseCount(Student $student): int
    {
        // The students' screens count them with the students.
        $counted = $student->getAttributes()['enrollments_count'] ?? null;

        return $counted !== null ? (int) $counted : $student->enrollments()->count();
    }

    /**
     * @return array<string, int|string>
     */
    private static function circleQuery(int|string|null $circleId, int|string|null $courseId): array
    {
        return filled($circleId) ? ['circle_id' => $circleId] : ['course_id' => (string) $courseId];
    }
}
