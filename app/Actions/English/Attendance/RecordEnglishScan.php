<?php

namespace App\Actions\English\Attendance;

use App\Enums\AttendanceMethod;
use App\Enums\ScanStatus;
use App\Exceptions\BusinessRuleViolation;
use App\Http\Requests\Attendance\ScanAttendanceRequest;
use App\Models\English\EnglishAttendance;
use App\Models\English\EnglishEnrollment;
use App\Models\English\EnglishSession;
use App\Models\Enrollment;
use App\Models\TeacherCard;
use App\Services\English\EnglishSessionResolver;
use App\Support\Dates;
use Carbon\CarbonInterface;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Records the attendance of a card scanned at the English section's desk:
 * today's, or that of an earlier day the desk was set to.
 *
 * The rules are the mosque's: the course was running, the day is one of
 * its class days or has a session already, and the student was in the
 * course on it. A card of the mosque is recognised as such, never taken
 * for an English one (the codes are never shared). Every expected
 * situation comes back as a result, never as an exception.
 */
final class RecordEnglishScan
{
    public function __construct(private readonly EnglishSessionResolver $sessions) {}

    /**
     * @param  CarbonInterface|null  $at  when the card was read (now by default)
     * @param  CarbonInterface|null  $day  the class day the attendance is for (the day of $at by default); never after today
     */
    public function handle(?string $cardCode, ?CarbonInterface $at = null, ?CarbonInterface $day = null): EnglishScanResult
    {
        $at ??= now();
        $day = Dates::parse(($day ?? $at)->toDateString());

        try {
            $data = ScanAttendanceRequest::validateData(['card_code' => $cardCode]);
        } catch (ValidationException) {
            return new EnglishScanResult(ScanStatus::UnknownCard);
        }

        $enrollment = EnglishEnrollment::query()
            ->with(['student', 'hall', 'course.schedules'])
            ->where('card_code', $data['card_code'])
            ->first();

        if ($enrollment === null) {
            return new EnglishScanResult($this->isMosqueCard($data['card_code']) ? ScanStatus::MosqueCard : ScanStatus::UnknownCard);
        }

        // Told with the day when it is not today.
        $shownDay = $day->isSameDay(Dates::today()) ? null : $day;
        $result = fn (ScanStatus $status, ?EnglishAttendance $attendance = null): EnglishScanResult => new EnglishScanResult($status, $enrollment, $attendance, $shownDay);

        if ($enrollment->left_on !== null && $day->toDateString() > $enrollment->left_on->toDateString()) {
            return $result(ScanStatus::InactiveEnrollment);
        }

        $course = $enrollment->course;

        if (! $course->isActive() || ! $course->covers($day)) {
            return $result(ScanStatus::CourseNotRunning);
        }

        if ($day->toDateString() < $enrollment->enrolled_on->toDateString()) {
            return $result(ScanStatus::BeforeEnrollment);
        }

        return DB::transaction(function () use ($enrollment, $course, $at, $day, $result): EnglishScanResult {
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
                $attendance = EnglishAttendance::query()->create([
                    'english_session_id' => $session->getKey(),
                    'english_enrollment_id' => $enrollment->getKey(),
                    'scanned_at' => $at,
                    'method' => AttendanceMethod::Qr,
                ]);
            } catch (UniqueConstraintViolationException) {
                // The same card was read twice at the same moment.
                return $result(ScanStatus::AlreadyRecorded, $this->findAttendance($session, $enrollment));
            }

            return $result(ScanStatus::Recorded, $attendance);
        });
    }

    private function isMosqueCard(string $code): bool
    {
        return Enrollment::query()->where('card_code', $code)->exists()
            || TeacherCard::query()->where('card_code', $code)->exists();
    }

    private function findAttendance(EnglishSession $session, EnglishEnrollment $enrollment): ?EnglishAttendance
    {
        return EnglishAttendance::query()
            ->where('english_session_id', $session->getKey())
            ->where('english_enrollment_id', $enrollment->getKey())
            ->first();
    }
}
