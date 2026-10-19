<?php

namespace App\Actions\Attendance;

use App\Enums\AttendanceMethod;
use App\Enums\ScanStatus;
use App\Exceptions\BusinessRuleViolation;
use App\Http\Requests\Attendance\ScanAttendanceRequest;
use App\Models\Attendance;
use App\Models\CourseSession;
use App\Models\Enrollment;
use App\Services\Attendance\SessionResolver;
use App\Services\Points\PointsLedger;
use App\Support\Dates;
use Carbon\CarbonInterface;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Records the attendance of a scanned card: today's, or that of an
 * earlier day the desk was set to (cards gathered on paper, a day the
 * computer was off).
 *
 * The day follows the same rules as today: the course was running, the
 * day is one of its class days or has a session already, and the
 * student was in the course on it.
 *
 * Every expected situation (unknown card, already recorded, not a class
 * day...) comes back as a ScanResult, never as an exception, so the scan
 * screen always has something to show.
 */
final class RecordScanAttendance
{
    public function __construct(
        private readonly SessionResolver $sessions,
        private readonly PointsLedger $points,
    ) {}

    /**
     * @param  CarbonInterface|null  $at  when the card was read (now by default)
     * @param  CarbonInterface|null  $day  the class day the attendance is for (the day of $at by default); never after today
     */
    public function handle(?string $cardCode, ?CarbonInterface $at = null, ?CarbonInterface $day = null): ScanResult
    {
        $at ??= now();
        $day = Dates::parse(($day ?? $at)->toDateString());

        try {
            $data = ScanAttendanceRequest::validateData(['card_code' => $cardCode]);
        } catch (ValidationException) {
            return new ScanResult(ScanStatus::UnknownCard);
        }

        $enrollment = Enrollment::query()
            ->with(['student', 'circle', 'course.schedules'])
            ->where('card_code', $data['card_code'])
            ->first();

        if ($enrollment === null) {
            return new ScanResult(ScanStatus::UnknownCard);
        }

        // Told with the day when it is not today.
        $shownDay = $day->isSameDay(Dates::today()) ? null : $day;
        $result = fn (ScanStatus $status, ?Attendance $attendance = null): ScanResult => new ScanResult($status, $enrollment, $attendance, day: $shownDay);

        $leftBefore = $enrollment->left_on !== null && $day->toDateString() > $enrollment->left_on->toDateString();

        if ($leftBefore || $enrollment->student->isArchived()) {
            return $result(ScanStatus::InactiveEnrollment);
        }

        $course = $enrollment->course;

        if (! $course->isActive() || ! $course->covers($day)) {
            return $result(ScanStatus::CourseNotRunning);
        }

        if ($day->toDateString() < $enrollment->enrolled_on->toDateString()) {
            return $result(ScanStatus::BeforeEnrollment);
        }

        return DB::transaction(function () use ($enrollment, $course, $at, $day, $result): ScanResult {
            try {
                $session = $this->sessions->forScan($course, $day);
            } catch (BusinessRuleViolation $violation) {
                return $result($violation->rule === 'session.cancelled' ? ScanStatus::SessionCancelled : ScanStatus::NotScheduledDay);
            }

            $existing = $this->findAttendance($session, $enrollment);

            if ($existing !== null) {
                return $result(ScanStatus::AlreadyRecorded, $existing);
            }

            try {
                $attendance = Attendance::query()->create([
                    'course_session_id' => $session->getKey(),
                    'enrollment_id' => $enrollment->getKey(),
                    'scanned_at' => $at,
                    'method' => AttendanceMethod::Qr,
                ]);
            } catch (UniqueConstraintViolationException) {
                // The same card was read twice at the same moment.
                return $result(ScanStatus::AlreadyRecorded, $this->findAttendance($session, $enrollment));
            }

            $attendance->setRelation('enrollment', $enrollment);
            $attendance->setRelation('session', $session);

            $this->points->awardForAttendance($attendance);

            return $result(ScanStatus::Recorded, $attendance);
        });
    }

    private function findAttendance(CourseSession $session, Enrollment $enrollment): ?Attendance
    {
        return Attendance::query()
            ->where('course_session_id', $session->getKey())
            ->where('enrollment_id', $enrollment->getKey())
            ->first();
    }
}
