<?php

namespace App\Actions\English\Attendance;

use App\Enums\AttendanceMethod;
use App\Exceptions\BusinessRuleViolation;
use App\Http\Requests\English\EnglishManualAttendanceRequest;
use App\Models\English\EnglishAttendance;
use App\Models\English\EnglishEnrollment;
use App\Services\English\EnglishEnrollmentCalendar;
use App\Services\English\EnglishSessionResolver;
use App\Support\Dates;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Attendance the admin adds by hand in an English course, for today or any
 * earlier date. It opens the session of that date if there is none yet.
 */
final class RecordEnglishManualAttendance
{
    public function __construct(
        private readonly EnglishSessionResolver $sessions,
        private readonly EnglishEnrollmentCalendar $calendar,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     *
     * @throws ValidationException
     * @throws BusinessRuleViolation attendance.duplicate | session.cancelled | date.*
     */
    public function handle(array $input): EnglishAttendance
    {
        $data = EnglishManualAttendanceRequest::validateData($input);

        $enrollment = EnglishEnrollment::query()->with(['course', 'student'])->findOrFail($data['english_enrollment_id']);
        $date = Dates::parse($data['attended_on']);

        $this->calendar->assertDateAllowed($enrollment, $date);

        return DB::transaction(function () use ($enrollment, $date): EnglishAttendance {
            $session = $this->sessions->forManual($enrollment->course, $date);

            $alreadyRecorded = EnglishAttendance::query()
                ->where('english_session_id', $session->getKey())
                ->where('english_enrollment_id', $enrollment->getKey())
                ->exists();

            if ($alreadyRecorded) {
                throw BusinessRuleViolation::because('attendance.duplicate', 'حضور الطالب مسجَّل مسبقاً في هذا التاريخ.');
            }

            return EnglishAttendance::query()->create([
                'english_session_id' => $session->getKey(),
                'english_enrollment_id' => $enrollment->getKey(),
                'scanned_at' => null,
                'method' => AttendanceMethod::Manual,
            ]);
        });
    }
}
