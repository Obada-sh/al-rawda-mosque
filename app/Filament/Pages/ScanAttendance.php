<?php

namespace App\Filament\Pages;

use App\Actions\Attendance\ScanCard;
use App\Actions\Attendance\ScanIntoExceptionalSession;
use App\Actions\Attendance\ScanResult;
use App\Enums\AttendanceMethod;
use App\Enums\ScanStatus;
use App\Filament\Enums\PanelGroup;
use App\Filament\Support\Attempt;
use App\Filament\Support\Attendance\BulkAttendanceAction;
use App\Filament\Support\Presenters\StudentPresenter;
use App\Filament\Support\Presenters\TeacherPresenter;
use App\Http\Requests\Attendance\ScanDayRequest;
use App\Models\Attendance;
use App\Models\TeacherAttendance;
use App\Services\Statistics\DashboardFigures;
use App\Support\Dates;
use BackedEnum;
use Carbon\CarbonInterface;
use Filament\Forms\Components\DatePicker;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use UnitEnum;

/**
 * The attendance desk. The USB scanner types the digits of the card and
 * presses Enter, exactly like a keyboard, so this screen is one text box
 * that is always ready, and a large coloured answer for each card.
 *
 * The same box takes the students' cards and the teachers'. The cards are
 * recorded for today, or for the earlier day the desk is set to (cards
 * gathered on paper, a day the computer was off); the desk says so in
 * large letters until it is set back to today.
 */
class ScanAttendance extends Page
{
    private const RECENT_SCANS = 8;

    protected static string|UnitEnum|null $navigationGroup = PanelGroup::Daily;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedQrCode;

    protected static ?int $navigationSort = 10;

    protected static ?string $navigationLabel = 'مسح الحضور';

    protected static ?string $title = 'مسح بطاقات الحضور';

    protected static ?string $slug = 'scan';

    protected string $view = 'filament.pages.scan-attendance';

    public string $cardCode = '';

    /** The day the cards are recorded for (Y-m-d): today, unless the admin chose an earlier day. */
    public ?string $day = null;

    /**
     * What the last scanned card led to (null until the first scan).
     *
     * @var array{tone: string, message: string, teacher: bool, name: ?string, photo: ?string, circle: ?string, course: ?string, sequence: int}|null
     */
    public ?array $result = null;

    public int $sequence = 0;

    /**
     * The card that was just refused because its day is not a class day of
     * its course, and that day (Y-m-d). While they are set, the screen
     * offers to open an exceptional session for it.
     */
    public ?string $refusedCard = null;

    public ?string $refusedDay = null;

    public function mount(): void
    {
        $this->day = Dates::today()->toDateString();
    }

    /**
     * Many students at once: ticked by name, or their card numbers typed
     * or scanned into one box.
     */
    protected function getHeaderActions(): array
    {
        return [
            BulkAttendanceAction::make(),
        ];
    }

    public function dayForm(Schema $schema): Schema
    {
        return $schema
            ->components([
                DatePicker::make('day')
                    ->label('تاريخ الحضور')
                    ->maxDate(fn () => Dates::today())
                    ->helperText('اليوم تلقائياً. غيّره لتسجيل بطاقات يوم سابق، ثم أعده إلى اليوم.')
                    ->live()
                    ->afterStateUpdated(function (): void {
                        $this->refusedCard = null;
                        $this->dispatch('scan-day-changed');
                    }),
            ]);
    }

    public function scan(ScanCard $scanCard): void
    {
        // Enter pressed on an empty box is not a card.
        if (trim($this->cardCode) === '') {
            return;
        }

        $cardCode = $this->cardCode;
        $this->cardCode = '';
        $day = $this->checkedDay($this->day);

        if ($day !== null) {
            $this->show($scanCard->handle($cardCode, day: $day), $cardCode, $day);
        }
    }

    /**
     * Opens the session of the refused card's day for its course (for a
     * teacher: of their card's course), although that day is not one of
     * its class days, and records the attendance.
     */
    public function openExceptionalSession(ScanIntoExceptionalSession $scanIntoExceptionalSession): void
    {
        $cardCode = $this->refusedCard;

        if ($cardCode === null) {
            return;
        }

        $day = $this->checkedDay($this->refusedDay);

        if ($day === null) {
            return;
        }

        $outcome = Attempt::button(fn (): ScanResult => $scanIntoExceptionalSession->handle($cardCode, day: $day));

        if ($outcome !== null) {
            $this->show($outcome, $cardCode, $day);
        }
    }

    public function backToToday(): void
    {
        $this->day = Dates::today()->toDateString();
        $this->refusedCard = null;
        $this->dispatch('scan-day-changed');
    }

    /**
     * The earlier day the desk records the cards for, as shown; null while
     * it records them for today (or the chosen day cannot be read).
     */
    public function anotherDay(): ?string
    {
        try {
            $day = Dates::parse(ScanDayRequest::validateData(['day' => $this->day])['day']);
        } catch (ValidationException) {
            return null;
        }

        return $day->isSameDay(Dates::today()) ? null : Dates::show($day);
    }

    /**
     * The button that opens an exceptional session says on which day.
     */
    public function exceptionalSessionLabel(): string
    {
        try {
            $day = Dates::parse(ScanDayRequest::validateData(['day' => $this->refusedDay])['day']);
        } catch (ValidationException) {
            $day = Dates::today();
        }

        return $day->isSameDay(Dates::today())
            ? 'فتح جلسة استثنائية اليوم وتسجيل الحضور'
            : 'فتح جلسة استثنائية يوم '.Dates::show($day).' وتسجيل الحضور';
    }

    /**
     * A day of the desk, checked; null (and the reason shown) when it is
     * not one the cards can be recorded for.
     */
    private function checkedDay(?string $day): ?CarbonInterface
    {
        try {
            return Dates::parse(ScanDayRequest::validateData(['day' => $day])['day']);
        } catch (ValidationException $exception) {
            $this->refusedCard = null;
            $this->answer('danger', (string) collect($exception->errors())->flatten()->first());

            return null;
        }
    }

    private function show(ScanResult $outcome, string $cardCode, CarbonInterface $day): void
    {
        $refused = $outcome->status === ScanStatus::NotScheduledDay;
        $this->refusedCard = $refused ? $cardCode : null;
        $this->refusedDay = $refused ? $day->toDateString() : null;

        $this->answer(
            tone: $this->tone($outcome),
            message: $outcome->message(),
            teacher: $outcome->isTeacher(),
            name: $outcome->name(),
            // The face of the card's owner, when they have a photo.
            photo: $outcome->isTeacher()
                ? TeacherPresenter::photoUrl($outcome->teacher)
                : StudentPresenter::photoUrl($outcome->enrollment?->student),
            circle: $outcome->circleName(),
            course: $outcome->courseName(),
        );
    }

    private function answer(string $tone, string $message, bool $teacher = false, ?string $name = null, ?string $photo = null, ?string $circle = null, ?string $course = null): void
    {
        $this->sequence++;
        $this->result = [
            'tone' => $tone,
            'message' => $message,
            'teacher' => $teacher,
            'name' => $name,
            'photo' => $photo,
            'circle' => $circle,
            'course' => $course,
            'sequence' => $this->sequence,
        ];

        // The page answers with a sound too: whoever holds the scanner is
        // looking at the cards, not at the screen.
        $this->dispatch('scan-processed', tone: $this->result['tone']);
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $figures = app(DashboardFigures::class);

        return [
            'recentScans' => $this->recentScans(),
            'attendanceToday' => $figures->attendanceToday(),
            'teachersToday' => $figures->teachersPresentToday(),
        ];
    }

    /**
     * Today's latest scanned cards, students' and teachers', newest first.
     * A teacher's card counts in each of their courses, but was scanned
     * once: it is listed once.
     *
     * @return Collection<int, array{key: string, teacher: bool, name: string, place: string, day: string|null, time: CarbonInterface}>
     */
    private function recentScans(): Collection
    {
        $today = [Dates::today(), Dates::today()->endOfDay()];

        $students = Attendance::query()
            ->with(['enrollment.student', 'enrollment.circle', 'session'])
            ->where('method', AttendanceMethod::Qr->value)
            ->whereBetween('scanned_at', $today)
            ->latest('scanned_at')
            ->latest('id')
            ->limit(self::RECENT_SCANS)
            ->get()
            ->map(fn (Attendance $attendance): array => [
                'key' => 'student-'.$attendance->getKey(),
                'teacher' => false,
                'name' => $attendance->enrollment->student->full_name,
                'place' => $attendance->enrollment->circle->name,
                'day' => self::earlierDay($attendance->session->session_date),
                'time' => $attendance->scanned_at,
            ]);

        $teachers = TeacherAttendance::query()
            ->with(['teacher', 'session.course'])
            ->where('method', AttendanceMethod::Qr->value)
            ->whereBetween('scanned_at', $today)
            ->latest('scanned_at')
            ->latest('id')
            ->limit(self::RECENT_SCANS * 3)
            ->get()
            ->groupBy(fn (TeacherAttendance $attendance): string => $attendance->teacher_id.'@'.$attendance->scanned_at->getTimestamp())
            ->map(fn (Collection $scan): array => [
                'key' => 'teacher-'.$scan->first()->getKey(),
                'teacher' => true,
                'name' => $scan->first()->teacher->full_name,
                'place' => $scan->map(fn (TeacherAttendance $attendance): string => $attendance->session->course->name)->unique()->implode('، '),
                'day' => self::earlierDay($scan->first()->session->session_date),
                'time' => $scan->first()->scanned_at,
            ])
            ->values();

        return $students
            ->concat($teachers)
            ->sortByDesc(fn (array $scan): int => $scan['time']->getTimestamp())
            ->take(self::RECENT_SCANS)
            ->values();
    }

    /**
     * The day of a session scanned today for an earlier day, as shown; null for today's.
     */
    private static function earlierDay(CarbonInterface $sessionDate): ?string
    {
        return $sessionDate->isSameDay(Dates::today()) ? null : Dates::show($sessionDate);
    }

    private function tone(ScanResult $outcome): string
    {
        return match (true) {
            $outcome->status->isSuccess() => 'success',
            $outcome->status->isWarning() => 'warning',
            default => 'danger',
        };
    }
}
