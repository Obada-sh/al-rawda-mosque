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
use Carbon\CarbonInterface;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Records today's attendance from a scanned card.
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

    public function handle(?string $cardCode, ?CarbonInterface $at = null): ScanResult
    {
        $at ??= now();

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

        if ($enrollment->hasLeft() || $enrollment->student->isArchived()) {
            return new ScanResult(ScanStatus::InactiveEnrollment, $enrollment);
        }

        $course = $enrollment->course;

        if (! $course->isActive() || ! $course->covers($at)) {
            return new ScanResult(ScanStatus::CourseNotRunning, $enrollment);
        }

        return DB::transaction(function () use ($enrollment, $course, $at): ScanResult {
            try {
                $session = $this->sessions->forScan($course, $at);
            } catch (BusinessRuleViolation $violation) {
                return new ScanResult(
                    $violation->rule === 'session.cancelled' ? ScanStatus::SessionCancelled : ScanStatus::NotScheduledDay,
                    $enrollment,
                );
            }

            $existing = $this->findAttendance($session, $enrollment);

            if ($existing !== null) {
                return new ScanResult(ScanStatus::AlreadyRecorded, $enrollment, $existing);
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
                return new ScanResult(ScanStatus::AlreadyRecorded, $enrollment, $this->findAttendance($session, $enrollment));
            }

            $attendance->setRelation('enrollment', $enrollment);
            $attendance->setRelation('session', $session);

            $this->points->awardForAttendance($attendance);

            return new ScanResult(ScanStatus::Recorded, $enrollment, $attendance);
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
