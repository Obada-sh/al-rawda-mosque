<?php

namespace App\Filament\English\Pages;

use App\Actions\English\Attendance\EnglishScanResult;
use App\Actions\English\Attendance\RecordEnglishScan;
use App\Actions\English\Attendance\ScanEnglishIntoExceptionalSession;
use App\Enums\AttendanceMethod;
use App\Enums\ScanStatus;
use App\Filament\English\EnglishPanelGroup;
use App\Filament\English\Support\EnglishBulkAttendanceAction;
use App\Filament\Support\Attempt;
use App\Filament\Support\Presenters\StudentPresenter;
use App\Http\Requests\Attendance\ScanDayRequest;
use App\Models\English\EnglishAttendance;
use App\Services\English\EnglishDashboardFigures;
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
 * The attendance desk of the English courses: the mosque's desk, for the
 * English section's cards. The USB scanner types the digits of the card
 * and presses Enter, so this screen is one text box that is always ready,
 * and a large coloured answer for each card.
 */
class ScanEnglishAttendance extends Page
{
    private const RECENT_SCANS = 8;

    protected static string|UnitEnum|null $navigationGroup = EnglishPanelGroup::Daily;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedQrCode;

    protected static ?int $navigationSort = 10;

    protected static ?string $navigationLabel = 'مسح الحضور';

    protected static ?string $title = 'مسح بطاقات الحضور';

    protected static ?string $slug = 'scan';

    protected string $view = 'filament.english.scan-attendance';

    public string $cardCode = '';

    /** The day the cards are recorded for (Y-m-d): today, unless the admin chose an earlier day. */
    public ?string $day = null;

    /**
     * What the last scanned card led to (null until the first scan).
     *
     * @var array{tone: string, message: string, name: ?string, photo: ?string, hall: ?string, course: ?string, sequence: int}|null
     */
    public ?array $result = null;

    public int $sequence = 0;

    /** The card just refused because its day is not a class day, and that day: an exceptional session is offered. */
    public ?string $refusedCard = null;

    public ?string $refusedDay = null;

    public function mount(): void
    {
        $this->day = Dates::today()->toDateString();
    }

    protected function getHeaderActions(): array
    {
        return [
            EnglishBulkAttendanceAction::make(),
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

    public function scan(RecordEnglishScan $recordScan): void
    {
        // Enter pressed on an empty box is not a card.
        if (trim($this->cardCode) === '') {
            return;
        }

        $cardCode = $this->cardCode;
        $this->cardCode = '';
        $day = $this->checkedDay($this->day);

        if ($day !== null) {
            $this->show($recordScan->handle($cardCode, day: $day), $cardCode, $day);
        }
    }

    /**
     * Opens the session of the refused card's day for its course, although
     * that day is not one of its class days, and records the attendance.
     */
    public function openExceptionalSession(ScanEnglishIntoExceptionalSession $scanIntoExceptionalSession): void
    {
        $cardCode = $this->refusedCard;

        if ($cardCode === null) {
            return;
        }

        $day = $this->checkedDay($this->refusedDay);

        if ($day === null) {
            return;
        }

        $outcome = Attempt::button(fn (): EnglishScanResult => $scanIntoExceptionalSession->handle($cardCode, day: $day));

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
     * it records them for today.
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

    private function show(EnglishScanResult $outcome, string $cardCode, CarbonInterface $day): void
    {
        $refused = $outcome->status === ScanStatus::NotScheduledDay;
        $this->refusedCard = $refused ? $cardCode : null;
        $this->refusedDay = $refused ? $day->toDateString() : null;

        $this->answer(
            tone: match (true) {
                $outcome->status->isSuccess() => 'success',
                $outcome->status->isWarning() => 'warning',
                default => 'danger',
            },
            message: $outcome->message(),
            name: $outcome->studentName(),
            photo: StudentPresenter::photoUrl($outcome->enrollment?->student),
            hall: $outcome->hallName(),
            course: $outcome->courseName(),
        );
    }

    private function answer(string $tone, string $message, ?string $name = null, ?string $photo = null, ?string $hall = null, ?string $course = null): void
    {
        $this->sequence++;
        $this->result = [
            'tone' => $tone,
            'message' => $message,
            'name' => $name,
            'photo' => $photo,
            'hall' => $hall,
            'course' => $course,
            'sequence' => $this->sequence,
        ];

        // The page answers with a sound too: whoever holds the scanner is
        // looking at the cards, not at the screen.
        $this->dispatch('scan-processed', tone: $tone);
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        return [
            'recentScans' => $this->recentScans(),
            'attendanceToday' => app(EnglishDashboardFigures::class)->attendanceToday(),
        ];
    }

    /**
     * Today's latest scanned cards, newest first.
     *
     * @return Collection<int, array{key: string, name: string, place: string, day: string|null, time: CarbonInterface}>
     */
    private function recentScans(): Collection
    {
        return EnglishAttendance::query()
            ->with(['enrollment.student', 'enrollment.hall', 'session'])
            ->where('method', AttendanceMethod::Qr->value)
            ->whereBetween('scanned_at', [Dates::today(), Dates::today()->endOfDay()])
            ->latest('scanned_at')
            ->latest('id')
            ->limit(self::RECENT_SCANS)
            ->get()
            ->map(fn (EnglishAttendance $attendance): array => [
                'key' => (string) $attendance->getKey(),
                'name' => $attendance->enrollment->student->full_name,
                'place' => $attendance->enrollment->hall->name,
                'day' => $attendance->session->session_date->isSameDay(Dates::today()) ? null : Dates::show($attendance->session->session_date),
                'time' => $attendance->scanned_at,
            ]);
    }
}
