<?php

namespace App\Actions\Attendance;

use App\Enums\AttendanceMethod;
use App\Enums\ScanStatus;
use App\Exceptions\BusinessRuleViolation;
use App\Models\CourseSession;
use App\Models\Teacher;
use App\Models\TeacherAttendance;
use App\Models\TeacherCard;
use App\Services\Attendance\SessionResolver;
use App\Services\Teachers\TeacherCircles;
use Carbon\CarbonInterface;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Records today's attendance of a teacher from their scanned card.
 *
 * A teacher's card belongs to one course (Services\Teachers\TeacherCards):
 * the attendance goes to today's session of that course, by the same rules
 * as the students' (the course is running, the session exists or today is
 * one of its days), as long as the teacher still supervises or assists an
 * active circle of it. Like the students' scan, every expected situation
 * comes back as a ScanResult, never as an exception.
 */
final class RecordTeacherScan
{
    public function __construct(
        private readonly SessionResolver $sessions,
        private readonly TeacherCircles $circles,
    ) {}

    public function handle(TeacherCard $card, ?CarbonInterface $at = null): ScanResult
    {
        $at ??= now();
        $card->loadMissing(['teacher', 'course.schedules']);
        $teacher = $card->teacher;
        $course = $card->course;

        $result = fn (ScanStatus $status, ?Collection $circles = null): ScanResult => new ScanResult(
            $status,
            teacher: $teacher,
            teacherCircles: $circles,
            teacherCourse: $course,
        );

        if (! $teacher->is_active) {
            return $result(ScanStatus::InactiveTeacher);
        }

        if (! $course->isActive() || ! $course->covers($at)) {
            return $result(ScanStatus::CourseNotRunning);
        }

        $circles = $this->circles->inCourse($teacher, $course);

        if ($circles->isEmpty()) {
            return $result(ScanStatus::TeacherWithoutCircle, $circles);
        }

        return DB::transaction(function () use ($teacher, $course, $circles, $at, $result): ScanResult {
            try {
                $session = $this->sessions->forScan($course, $at);
            } catch (BusinessRuleViolation $violation) {
                return $result(
                    $violation->rule === 'session.cancelled' ? ScanStatus::SessionCancelled : ScanStatus::NotScheduledDay,
                    $circles,
                );
            }

            if ($this->isRecorded($session, $teacher)) {
                return $result(ScanStatus::AlreadyRecorded, $circles);
            }

            try {
                TeacherAttendance::query()->create([
                    'course_session_id' => $session->getKey(),
                    'teacher_id' => $teacher->getKey(),
                    'scanned_at' => $at,
                    'method' => AttendanceMethod::Qr,
                ]);
            } catch (UniqueConstraintViolationException) {
                // The same card was read twice at the same moment.
                return $result(ScanStatus::AlreadyRecorded, $circles);
            }

            return $result(ScanStatus::Recorded, $circles);
        });
    }

    private function isRecorded(CourseSession $session, Teacher $teacher): bool
    {
        return TeacherAttendance::query()
            ->where('course_session_id', $session->getKey())
            ->where('teacher_id', $teacher->getKey())
            ->exists();
    }
}
