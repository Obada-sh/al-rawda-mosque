<?php

namespace App\Filament\Support\Documents;

use App\Actions\Documents\SheetOptions;
use App\Actions\Documents\StudentFiles;
use App\Filament\Support\Attempt;
use App\Filament\Support\NewWindow;
use App\Filament\Support\Options;
use App\Http\Requests\Documents\CircleSheetRequest;
use App\Http\Requests\Documents\MosqueSheetRequest;
use App\Http\Requests\Documents\StudentFileRequest;
use App\Http\Requests\Documents\StudentFilesRequest;
use App\Models\Circle;
use App\Models\Student;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Notifications\Notification;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Component as Livewire;

/**
 * The buttons that open the documents printed on A4 paper: a student's
 * file, the files of many students, the list of a circle's students or
 * their attendance codes, and the list of every student of the mosque.
 * They only open the page, in a new window; what is printed is decided and
 * checked by PrepareStudentFile, PrepareStudentFiles, PrepareCircleSheets
 * and PrepareMosqueSheet.
 */
final class DocumentActions
{
    private const LIST_DESCRIPTION = 'صفحة A4 تحت شعار الجامع، تُفتح في نافذة جديدة للطباعة أو للحفظ بصيغة PDF.';

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
     * The files of many students, from the choice StudentFilesRequest
     * validated: scope, and course_id, circle_id, archived, history or
     * student_ids as the scope needs.
     *
     * @param  array<string, mixed>  $choice
     */
    public static function studentFilesUrl(array $choice): string
    {
        $query = ['scope' => (string) $choice['scope']];

        foreach (['course_id', 'circle_id', 'history'] as $key) {
            if (filled($choice[$key] ?? null)) {
                $query[$key] = (string) $choice[$key];
            }
        }

        if ((bool) ($choice['archived'] ?? false)) {
            $query['archived'] = '1';
        }

        if (($choice['student_ids'] ?? []) !== []) {
            $query['student_ids'] = array_values(array_map('intval', (array) $choice['student_ids']));
        }

        return route('filament.admin.print.student-files', $query);
    }

    /**
     * The list of the students of one circle, or of every circle of a
     * course: with every column and the records of the course, unless the
     * options say otherwise.
     */
    public static function circleStudentsUrl(int|string|null $circleId = null, int|string|null $courseId = null, ?SheetOptions $options = null): string
    {
        return route('filament.admin.print.circle-students', self::circleQuery($circleId, $courseId) + ($options?->query() ?? []));
    }

    /**
     * The list of the mosque's students: every student in the system with
     * their records in every course (archived ones too when asked), or,
     * when the options say one course, the students of that course.
     */
    public static function mosqueStudentsUrl(SheetOptions $options, int|string|null $courseId = null, bool $withArchived = false): string
    {
        $scope = $options->allCourses
            ? ($withArchived ? ['archived' => '1'] : [])
            : ['course_id' => (string) $courseId];

        return route('filament.admin.print.mosque-students', $scope + $options->query());
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
            ->action(function (Student $record, array $data, Action $action, Livewire $livewire): void {
                $data = Attempt::action($action, fn (): array => StudentFileRequest::validateData($data, ['student_id' => $record->getKey()]));

                NewWindow::open($livewire, self::studentFileUrl($record->getKey(), $data['course_id'] ?? null));
            });
    }

    /**
     * At the top of the students' list: the files of every student, of the
     * students of a course, or of those of a circle; each on pages of its
     * own, to print together or save as one PDF.
     */
    public static function studentFiles(): Action
    {
        $scope = fn (Get $get): string => (string) $get('scope');

        return Action::make('studentFiles')
            ->label('ملفات A4')
            ->icon(Heroicon::OutlinedDocumentDuplicate)
            ->color('gray')
            ->modalHeading('ملفات الطلاب على ورق A4')
            ->modalDescription('ملف كل طالب يبدأ بصفحة جديدة، والطلاب مرتّبون بأسمائهم. '.self::LIST_DESCRIPTION)
            ->modalSubmitActionLabel('فتح صفحة الطباعة')
            ->modalWidth('lg')
            ->schema([
                Radio::make('scope')
                    ->label('ملفات من')
                    ->options([
                        StudentFiles::ALL => 'كل الطلاب',
                        StudentFiles::COURSE => 'طلاب دورة',
                        StudentFiles::CIRCLE => 'طلاب حلقة',
                    ])
                    ->descriptions([
                        StudentFiles::ALL => 'كل طالب مسجّل في النظام، حتى من لم يدخل دورة بعد.',
                        StudentFiles::COURSE => 'الطلاب الحاليون في الدورة التي تختارها، من كل حلقاتها.',
                        StudentFiles::CIRCLE => 'الطلاب الحاليون في الحلقة التي تختارها.',
                    ])
                    ->default(StudentFiles::ALL)
                    ->live(),
                Toggle::make('archived')
                    ->label('مع الطلاب المؤرشفين')
                    ->default(false)
                    ->visible(fn (Get $get): bool => $scope($get) === StudentFiles::ALL),
                Select::make('course_id')
                    ->label('الدورة')
                    ->options(fn (): array => Options::courses())
                    ->default(fn (): ?int => Options::soleActiveCourseId())
                    ->markAsRequired()
                    ->live()
                    ->afterStateUpdated(fn (Set $set) => $set('circle_id', null))
                    ->visible(fn (Get $get): bool => in_array($scope($get), [StudentFiles::COURSE, StudentFiles::CIRCLE], true)),
                Select::make('circle_id')
                    ->label('الحلقة')
                    ->placeholder(fn (Get $get): string => blank($get('course_id')) ? 'اختر الدورة أولاً' : 'اختر الحلقة')
                    ->options(fn (Get $get): array => Options::circlesOfCourse($get('course_id')))
                    ->markAsRequired()
                    ->visible(fn (Get $get): bool => $scope($get) === StudentFiles::CIRCLE),
                Radio::make('history')
                    ->label('في كل ملف')
                    ->options([
                        SheetOptions::ALL_COURSES => 'كل دورات الطالب',
                        SheetOptions::THIS_COURSE => 'هذه الدورة فقط',
                    ])
                    ->default(SheetOptions::ALL_COURSES)
                    ->visible(fn (Get $get): bool => in_array($scope($get), [StudentFiles::COURSE, StudentFiles::CIRCLE], true)),
            ])
            ->action(function (array $data, Action $action, Livewire $livewire): void {
                $choice = Attempt::action($action, fn (): array => StudentFilesRequest::validateData($data));

                NewWindow::open($livewire, self::studentFilesUrl($choice));
            });
    }

    /**
     * For the students ticked in the list: their files, every course of each.
     */
    public static function chosenStudentFiles(): BulkAction
    {
        return BulkAction::make('studentFiles')
            ->label('ملفات A4 للمحدَّدين')
            ->icon(Heroicon::OutlinedDocumentDuplicate)
            ->action(function (Collection $records, Livewire $livewire): void {
                if ($records->count() > StudentFilesRequest::MAX_CHOSEN) {
                    Notification::make()
                        ->title('تُطبع ملفات '.StudentFilesRequest::MAX_CHOSEN.' طالب على الأكثر في المرة الواحدة.')
                        ->warning()
                        ->send();

                    return;
                }

                $ids = $records->modelKeys();
                sort($ids);

                $choice = StudentFilesRequest::validateData(['scope' => StudentFiles::CHOSEN, 'student_ids' => $ids]);

                NewWindow::open($livewire, self::studentFilesUrl($choice));
            })
            ->deselectRecordsAfterCompletion();
    }

    /**
     * In a circle's row: the list of its students, with the records and
     * the columns the admin chooses.
     */
    public static function circleStudents(): Action
    {
        return Action::make('circleStudents')
            ->label('كشف الطلاب')
            ->icon(Heroicon::OutlinedTableCells)
            ->color('gray')
            ->modalHeading(fn (Circle $record): string => "كشف طلاب {$record->name}")
            ->modalDescription(self::LIST_DESCRIPTION)
            ->modalSubmitActionLabel('فتح صفحة الطباعة')
            ->modalWidth('lg')
            ->schema(self::listChoices())
            ->action(function (Circle $record, array $data, Action $action, Livewire $livewire): void {
                $options = self::chosenOptions($data);

                Attempt::action($action, fn (): array => CircleSheetRequest::validateData(['circle_id' => $record->getKey()] + $options->query()));

                NewWindow::open($livewire, self::circleStudentsUrl($record->getKey(), options: $options));
            });
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
        return self::wholeCourse('courseStudents', 'كشوف كل الحلقات', Heroicon::OutlinedTableCells, self::listChoices())
            ->action(function (array $data, Action $action, Livewire $livewire): void {
                $options = self::chosenOptions($data);
                $valid = Attempt::action($action, fn (): array => CircleSheetRequest::validateData(['course_id' => $data['course_id'] ?? null] + $options->query()));

                NewWindow::open($livewire, self::circleStudentsUrl(courseId: $valid['course_id'], options: $options));
            });
    }

    /**
     * Above the circles: the list of the mosque's students, without
     * choosing a circle. Every student in the system with their whole
     * record, or the students of one course with what they did in it.
     */
    public static function mosqueStudents(): Action
    {
        return Action::make('mosqueStudents')
            ->label('كشف كل طلاب الجامع')
            ->icon(Heroicon::OutlinedUserGroup)
            ->color('gray')
            ->modalHeading('كشف كل طلاب الجامع')
            ->modalDescription('جدول واحد مرتّب بأسماء الطلاب، بجانب كل طالب حلقته. '.self::LIST_DESCRIPTION)
            ->modalSubmitActionLabel('فتح صفحة الطباعة')
            ->modalWidth('lg')
            ->schema([
                Radio::make('history')
                    ->label('الطلاب وسجلّهم')
                    ->options([
                        SheetOptions::ALL_COURSES => 'كل الدورات',
                        SheetOptions::THIS_COURSE => 'دورة واحدة',
                    ])
                    ->descriptions([
                        SheetOptions::ALL_COURSES => 'كل طالب مسجّل في النظام، حتى من لم يدخل دورة بعد، بسجلّه منذ دخوله: كل ما حفظه، وكل سبر، وحضوره في كل دوراته. الحلقة هي حلقته الآن.',
                        SheetOptions::THIS_COURSE => 'طلاب الدورة التي تختارها من كل حلقاتها، بما فعلوه فيها.',
                    ])
                    ->default(SheetOptions::ALL_COURSES)
                    ->live(),
                Select::make('course_id')
                    ->label('الدورة')
                    ->options(fn (): array => Options::courses())
                    ->default(fn (): ?int => Options::soleActiveCourseId())
                    ->markAsRequired()
                    ->visible(fn (Get $get): bool => $get('history') === SheetOptions::THIS_COURSE),
                Toggle::make('archived')
                    ->label('مع الطلاب المؤرشفين')
                    ->helperText('يُكتب تحت اسم كل واحد منهم أن ملفه مؤرشف.')
                    ->default(false)
                    ->visible(fn (Get $get): bool => $get('history') === SheetOptions::ALL_COURSES),
                self::columnsChoice(),
            ])
            ->action(function (array $data, Action $action, Livewire $livewire): void {
                $options = self::chosenOptions($data);
                $valid = Attempt::action($action, fn (): array => MosqueSheetRequest::validateData([
                    'course_id' => $data['course_id'] ?? null,
                    'archived' => (bool) ($data['archived'] ?? false),
                ] + $options->query()));

                NewWindow::open($livewire, self::mosqueStudentsUrl($options, $valid['course_id'] ?? null, (bool) ($valid['archived'] ?? false)));
            });
    }

    /**
     * Above the circles: the attendance codes of every circle of the chosen course.
     */
    public static function courseCodes(): Action
    {
        return self::wholeCourse('courseCodes', 'رموز كل الحلقات', Heroicon::OutlinedQrCode)
            ->action(function (array $data, Action $action, Livewire $livewire): void {
                $data = Attempt::action($action, fn (): array => CircleSheetRequest::validateData($data));

                NewWindow::open($livewire, self::circleCodesUrl(courseId: $data['course_id']));
            });
    }

    /**
     * @param  list<Component>  $choices  what else the window asks
     */
    private static function wholeCourse(string $name, string $label, Heroicon $icon, array $choices = []): Action
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
                ...$choices,
            ]);
    }

    /**
     * What the list of the students shows: whose records, and which columns.
     *
     * @return list<Component>
     */
    private static function listChoices(): array
    {
        return [
            Radio::make('history')
                ->label('السجلّ')
                ->options([
                    SheetOptions::THIS_COURSE => 'هذه الدورة فقط',
                    SheetOptions::ALL_COURSES => 'كل الدورات',
                ])
                ->descriptions([
                    SheetOptions::THIS_COURSE => 'ما فعله كل طالب في دورة الحلقة.',
                    SheetOptions::ALL_COURSES => 'سجلّ كل طالب منذ دخوله النظام: كل ما حفظه، وكل سبر، وحضوره في كل دوراته. يناسب أول الدورة، ليعرف الأستاذ طلابه الجدد.',
                ])
                ->default(SheetOptions::THIS_COURSE),
            self::columnsChoice(),
        ];
    }

    /**
     * Which columns the list of the students shows next to the names.
     */
    private static function columnsChoice(): CheckboxList
    {
        return CheckboxList::make('columns')
            ->label('ما يظهر بجانب اسم الطالب')
            ->options([
                'phones' => 'رقم الطالب ورقم ولي الأمر',
                'pages' => 'الصفحات المحفوظة: عددها وأرقامها',
                'exams' => 'السبر: الأجزاء وتقديراتها',
                'attendance' => 'الحضور',
            ])
            ->default(SheetOptions::COLUMNS)
            ->helperText('أزل العلامة عمّا لا تريد طباعته. بلا أي علامة يُطبع كشف بالأسماء وحدها.');
    }

    /**
     * @param  array<string, mixed>  $data  the window's choices
     */
    private static function chosenOptions(array $data): SheetOptions
    {
        $columns = array_values(array_filter((array) ($data['columns'] ?? []), 'is_string'));

        return SheetOptions::chosen((string) ($data['history'] ?? SheetOptions::THIS_COURSE), $columns);
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
