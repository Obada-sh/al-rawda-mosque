<?php

namespace App\Filament\Support\Cards;

use App\Filament\Support\Attempt;
use App\Filament\Support\NewWindow;
use App\Filament\Support\Options;
use App\Http\Requests\Cards\PrintCardsRequest;
use App\Http\Requests\Cards\PrintTeacherCardsRequest;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Student;
use App\Models\Teacher;
use App\Services\Teachers\TeacherCircles;
use Closure;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Component;

/**
 * The buttons that open the pages printing the attendance cards, wherever
 * the admin looks for them: the students' and the teachers' lists, a
 * student's or a teacher's row, a course. They only open the page; what is
 * printed is decided and checked by PrepareAttendanceCards and
 * PrepareTeacherCards. Cards always belong to a course.
 */
final class CardPrintActions
{
    /**
     * The print page of the cards of a course, of one of its circles, or
     * of chosen students of it.
     *
     * @param  list<int|string>  $enrollmentIds
     */
    public static function url(int|string $courseId, int|string|null $circleId = null, array $enrollmentIds = []): string
    {
        return route('filament.admin.attendance-cards.print', array_filter([
            'course_id' => $courseId,
            'circle_id' => $circleId,
            'enrollment_ids' => array_values($enrollmentIds),
        ], fn (mixed $value): bool => filled($value)));
    }

    /**
     * At the top of the students' list: the admin picks the course (the
     * running one is already chosen when there is only one) and, if they
     * like, one circle of it.
     */
    public static function choose(): Action
    {
        return Action::make('printCards')
            ->label('طباعة البطاقات')
            ->icon(Heroicon::OutlinedPrinter)
            ->color('gray')
            ->modalHeading('طباعة بطاقات الحضور')
            ->modalDescription('تُفتح صفحة الطباعة في نافذة جديدة، وفيها تختار ورقة A4 (9 بطاقات) أو بطاقة في كل صفحة.')
            ->modalSubmitActionLabel('فتح صفحة الطباعة')
            ->modalWidth('lg')
            ->schema([
                Select::make('course_id')
                    ->label('الدورة')
                    ->options(fn (): array => Options::courses())
                    ->default(fn (): ?int => Options::soleActiveCourseId())
                    ->markAsRequired()
                    ->live()
                    ->afterStateUpdated(fn (Set $set) => $set('circle_id', null)),
                Select::make('circle_id')
                    ->label('الحلقة')
                    ->placeholder(fn (Get $get): string => blank($get('course_id')) ? 'اختر الدورة أولاً' : 'كل الحلقات')
                    ->options(fn (Get $get): array => Options::circlesOfCourse($get('course_id')))
                    ->disabled(fn (Get $get): bool => blank($get('course_id'))),
            ])
            ->action(function (array $data, Action $action, Component $livewire): void {
                $data = Attempt::action($action, fn (): array => PrintCardsRequest::validateData($data));

                NewWindow::open($livewire, self::url($data['course_id'], $data['circle_id'] ?? null));
            });
    }

    /**
     * In a student's row: the card of the course the student is in now
     * (the newest, for a student of two running courses).
     */
    public static function forStudent(): Action
    {
        return Action::make('card')
            ->label('البطاقة')
            ->icon(Heroicon::OutlinedQrCode)
            ->color('gray')
            ->visible(fn (Student $record): bool => self::currentEnrollment($record) !== null)
            ->url(function (Student $record): ?string {
                $enrollment = self::currentEnrollment($record);

                return $enrollment === null ? null : self::url($enrollment->course_id, enrollmentIds: [$enrollment->getKey()]);
            })
            ->openUrlInNewTab();
    }

    /**
     * For the students ticked in the list: their cards in the chosen course.
     */
    public static function forSelected(): BulkAction
    {
        return BulkAction::make('printCards')
            ->label('طباعة بطاقات المحدَّدين')
            ->icon(Heroicon::OutlinedPrinter)
            ->modalHeading('طباعة بطاقات الطلاب المحدَّدين')
            ->modalSubmitActionLabel('فتح صفحة الطباعة')
            ->modalWidth('lg')
            ->schema([
                Select::make('course_id')
                    ->label('الدورة')
                    ->options(fn (): array => Options::courses())
                    ->default(fn (): ?int => Options::soleActiveCourseId())
                    ->markAsRequired(),
            ])
            ->action(function (Collection $records, array $data, BulkAction $action, Component $livewire): void {
                $data = Attempt::action($action, fn (): array => PrintCardsRequest::validateData($data));

                $enrollmentIds = Enrollment::query()
                    ->where('course_id', $data['course_id'])
                    ->whereNull('left_on')
                    ->whereIn('student_id', $records->modelKeys())
                    ->orderBy('id')
                    ->pluck('id')
                    ->all();

                if ($enrollmentIds === []) {
                    Notification::make()
                        ->title('لا أحد من الطلاب المحدَّدين مسجَّل في هذه الدورة.')
                        ->warning()
                        ->send();

                    return;
                }

                NewWindow::open($livewire, self::url($data['course_id'], enrollmentIds: $enrollmentIds));
            })
            ->deselectRecordsAfterCompletion();
    }

    /**
     * At the top of a course's page: the cards of all its current students.
     *
     * @param  Closure(): Course  $course
     */
    public static function forCourse(Closure $course): Action
    {
        return Action::make('printCards')
            ->label('بطاقات الطلاب')
            ->icon(Heroicon::OutlinedPrinter)
            ->color('gray')
            ->url(fn (): string => self::url($course()->getKey()))
            ->openUrlInNewTab();
    }

    /**
     * The print page of the cards of a course's teachers, or of chosen ones.
     *
     * @param  list<int|string>  $teacherIds
     */
    public static function teachersUrl(int|string $courseId, array $teacherIds = []): string
    {
        return route('filament.admin.teacher-cards.print', array_filter([
            'course_id' => $courseId,
            'teacher_ids' => array_values($teacherIds),
        ], fn (mixed $value): bool => filled($value)));
    }

    /**
     * At the top of the teachers' list: the cards of the teachers of the
     * chosen course.
     */
    public static function chooseTeachersCourse(): Action
    {
        return Action::make('cards')
            ->label('بطاقات الأساتذة')
            ->icon(Heroicon::OutlinedPrinter)
            ->color('gray')
            ->modalHeading('طباعة بطاقات الأساتذة')
            ->modalDescription('لكل دورة بطاقات أساتذتها: بطاقة الأستاذ يُسجَّل بها حضوره في تلك الدورة فقط. تُطبع بطاقات كل من يشرف على حلقة نشطة منها أو يساعد فيها.')
            ->modalSubmitActionLabel('فتح صفحة الطباعة')
            ->modalWidth('lg')
            ->schema([self::courseField(fn (): array => Options::activeCourses())])
            ->action(function (array $data, Action $action, Component $livewire): void {
                $data = Attempt::action($action, fn (): array => PrintTeacherCardsRequest::validateData($data));

                NewWindow::open($livewire, self::teachersUrl($data['course_id']));
            });
    }

    /**
     * In a teacher's row: their card in a running course they teach in.
     * With one such course the page opens at once; with more, the admin
     * picks the course.
     */
    public static function forTeacher(): Action
    {
        return Action::make('card')
            ->label('البطاقة')
            ->icon(Heroicon::OutlinedQrCode)
            ->color('gray')
            ->visible(fn (Teacher $record): bool => Options::activeCoursesOfTeacher($record->getKey()) !== [])
            ->url(function (Teacher $record): ?string {
                $courses = Options::activeCoursesOfTeacher($record->getKey());

                return count($courses) === 1 ? self::teachersUrl((int) array_key_first($courses), [$record->getKey()]) : null;
            })
            ->openUrlInNewTab()
            ->modalHeading(fn (Teacher $record): string => "بطاقة {$record->full_name}")
            ->modalSubmitActionLabel('فتح صفحة الطباعة')
            ->modalWidth('lg')
            ->schema(fn (Teacher $record): array => [
                // The newest of the teacher's running courses is chosen already.
                self::courseField(
                    fn (): array => Options::activeCoursesOfTeacher($record->getKey()),
                    fn (): ?int => array_key_first(Options::activeCoursesOfTeacher($record->getKey())),
                ),
            ])
            ->action(function (Teacher $record, array $data, Action $action, Component $livewire): void {
                $data = Attempt::action($action, fn (): array => PrintTeacherCardsRequest::validateData($data));

                NewWindow::open($livewire, self::teachersUrl($data['course_id'], [$record->getKey()]));
            });
    }

    /**
     * For the teachers ticked in the list: their cards in the chosen course.
     */
    public static function forSelectedTeachers(): BulkAction
    {
        return BulkAction::make('printCards')
            ->label('طباعة بطاقات المحدَّدين')
            ->icon(Heroicon::OutlinedPrinter)
            ->modalHeading('طباعة بطاقات الأساتذة المحدَّدين')
            ->modalSubmitActionLabel('فتح صفحة الطباعة')
            ->modalWidth('lg')
            ->schema([self::courseField(fn (): array => Options::activeCourses())])
            ->action(function (Collection $records, array $data, BulkAction $action, Component $livewire, TeacherCircles $circles): void {
                $data = Attempt::action($action, fn (): array => PrintTeacherCardsRequest::validateData($data));

                $course = Course::query()->findOrFail($data['course_id']);
                $teacherIds = $circles->teachersOf($course)->pluck('id')->all();
                $chosen = array_values(array_intersect($teacherIds, $records->modelKeys()));

                if ($chosen === []) {
                    Notification::make()
                        ->title('لا أحد من الأساتذة المحدَّدين يشرف على حلقة من هذه الدورة أو يساعد فيها.')
                        ->warning()
                        ->send();

                    return;
                }

                NewWindow::open($livewire, self::teachersUrl($course->getKey(), $chosen));
            })
            ->deselectRecordsAfterCompletion();
    }

    /**
     * At the top of a course's page: the cards of its teachers.
     *
     * @param  Closure(): Course  $course
     */
    public static function forCourseTeachers(Closure $course): Action
    {
        return Action::make('printTeacherCards')
            ->label('بطاقات الأساتذة')
            ->icon(Heroicon::OutlinedIdentification)
            ->color('gray')
            ->url(fn (): string => self::teachersUrl($course()->getKey()))
            ->openUrlInNewTab();
    }

    /**
     * @param  Closure(): array<int, string>  $options
     * @param  (Closure(): ?int)|null  $default  the course chosen already; by default the only running one, if there is only one
     */
    private static function courseField(Closure $options, ?Closure $default = null): Select
    {
        return Select::make('course_id')
            ->label('الدورة')
            ->options($options)
            ->default($default ?? fn (): ?int => Options::soleActiveCourseId())
            ->markAsRequired();
    }

    private static function currentEnrollment(Student $student): ?Enrollment
    {
        // The list loads the enrollments that are current in a running course.
        return $student->enrollments
            ->sortByDesc(fn (Enrollment $enrollment): string => $enrollment->course->start_date->toDateString())
            ->first();
    }
}
