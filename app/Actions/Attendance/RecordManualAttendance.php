<?php

namespace App\Actions\Attendance;

use App\Enums\AttendanceMethod;
use App\Exceptions\BusinessRuleViolation;
use App\Http\Requests\Attendance\ManualAttendanceRequest;
use App\Models\Attendance;
use App\Models\Enrollment;
use App\Services\Attendance\SessionResolver;
use App\Services\Enrollments\EnrollmentCalendar;
use App\Services\Points\PointsLedger;
use App\Support\Dates;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Attendance the admin adds by hand, for today or any earlier date.
 * It earns the same points as a scan, and opens the session of that date
 * if there is none yet.
 */
final class RecordManualAttendance
{
    public function __construct(
        private readonly SessionResolver $sessions,
        private readonly EnrollmentCalendar $calendar,
        private readonly PointsLedger $points,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     *
     * @throws ValidationException
     * @throws BusinessRuleViolation attendance.duplicate | session.cancelled | date.*
     */
    public function handle(array $input): Attendance
    {
        $data = ManualAttendanceRequest::validateData($input);

        $enrollment = Enrollment::query()->with(['course', 'student'])->findOrFail($data['enrollment_id']);
        $date = Dates::parse($data['attended_on']);

        $this->calendar->assertDateAllowed($enrollment, $date);

        return DB::transaction(function () use ($enrollment, $date): Attendance {
            $session = $this->sessions->forManual($enrollment->course, $date);

            $alreadyRecorded = Attendance::query()
                ->where('course_session_id', $session->getKey())
                ->where('enrollment_id', $enrollment->getKey())
                ->exists();

            if ($alreadyRecorded) {
                throw BusinessRuleViolation::because('attendance.duplicate', 'حضور الطالب مسجَّل مسبقاً في هذا التاريخ.');
            }

            $attendance = Attendance::query()->create([
                'course_session_id' => $session->getKey(),
                'enrollment_id' => $enrollment->getKey(),
                'scanned_at' => null,
                'method' => AttendanceMethod::Manual,
            ]);

            $attendance->setRelation('enrollment', $enrollment);
            $attendance->setRelation('session', $session);

            $this->points->awardForAttendance($attendance);

            return $attendance;
        });
    }
}
