<?php

namespace App\Filament\Pages;

use App\Actions\Attendance\ScanCard;
use App\Actions\Attendance\ScanIntoExceptionalSession;
use App\Actions\Attendance\ScanResult;
use App\Enums\AttendanceMethod;
use App\Enums\ScanStatus;
use App\Filament\Enums\PanelGroup;
use App\Filament\Support\Attempt;
use App\Filament\Support\Presenters\StudentPresenter;
use App\Filament\Support\Presenters\TeacherPresenter;
use App\Models\Attendance;
use App\Models\TeacherAttendance;
use App\Services\Statistics\DashboardFigures;
use App\Support\Dates;
use BackedEnum;
use Carbon\CarbonInterface;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use UnitEnum;

/**
 * The attendance desk. The USB scanner types the digits of the card and
 * presses Enter, exactly like a keyboard, so this screen is one text box
 * that is always ready, and a large coloured answer for each card.
 *
 * The same box takes the students' cards and the teachers'.
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

    /**
     * What the last scanned card led to (null until the first scan).
     *
     * @var array{tone: string, message: string, teacher: bool, name: ?string, photo: ?string, circle: ?string, course: ?string, sequence: int}|null
     */
    public ?array $result = null;

    public int $sequence = 0;

    /**
     * The card that was just refused because today is not a class day of
     * its course. While it is set, the screen offers to open an
     * exceptional session for it.
     */
    public ?string $refusedCard = null;

    public function scan(ScanCard $scanCard): void
    {
        // Enter pressed on an empty box is not a card.
        if (trim($this->cardCode) === '') {
            return;
        }

        $cardCode = $this->cardCode;
        $this->cardCode = '';

        $this->show($scanCard->handle($cardCode), $cardCode);
    }

    /**
     * Opens today's session for the course of the refused card (for a
     * teacher: of their courses), although today is not one of its class
     * days, and records the attendance.
     */
    public function openExceptionalSession(ScanIntoExceptionalSession $scanIntoExceptionalSession): void
    {
        if ($this->refusedCard === null) {
            return;
        }

        $cardCode = $this->refusedCard;
        $outcome = Attempt::button(fn (): ScanResult => $scanIntoExceptionalSession->handle($cardCode));

        if ($outcome !== null) {
            $this->show($outcome, $cardCode);
        }
    }

    private function show(ScanResult $outcome, string $cardCode): void
    {
        $this->refusedCard = $outcome->status === ScanStatus::NotScheduledDay ? $cardCode : null;
        $this->sequence++;
        $this->result = [
            'tone' => $this->tone($outcome),
            'message' => $outcome->message(),
            'teacher' => $outcome->isTeacher(),
            'name' => $outcome->name(),
            // The face of the card's owner, when they have a photo.
            'photo' => $outcome->isTeacher()
                ? TeacherPresenter::photoUrl($outcome->teacher)
                : StudentPresenter::photoUrl($outcome->enrollment?->student),
            'circle' => $outcome->circleName(),
            'course' => $outcome->courseName(),
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
     * @return Collection<int, array{key: string, teacher: bool, name: string, place: string, time: CarbonInterface}>
     */
    private function recentScans(): Collection
    {
        $today = [Dates::today(), Dates::today()->endOfDay()];

        $students = Attendance::query()
            ->with(['enrollment.student', 'enrollment.circle'])
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
                'time' => $scan->first()->scanned_at,
            ])
            ->values();

        return $students
            ->concat($teachers)
            ->sortByDesc(fn (array $scan): int => $scan['time']->getTimestamp())
            ->take(self::RECENT_SCANS)
            ->values();
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
