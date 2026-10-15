<?php

namespace App\Actions\Attendance;

use App\Enums\AttendanceMethod;
use App\Exceptions\BusinessRuleViolation;
use App\Http\Requests\Attendance\TeacherAttendanceRequest;
use App\Models\Course;
use App\Models\Teacher;
use App\Models\TeacherAttendance;
use App\Services\Attendance\SessionResolver;
use App\Services\Teachers\TeacherCircles;
use App\Support\Dates;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * A teacher's attendance added by hand (the card was forgotten), for today
 * or an earlier day of a course in which the teacher supervises or assists
 * a circle. It opens the session of that day if there is none yet.
 */
final class RecordManualTeacherAttendance
{
    public function __construct(
        private readonly SessionResolver $sessions,
        private readonly TeacherCircles $circles,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     *
     * @throws ValidationException
     * @throws BusinessRuleViolation teacher.not_in_course | session.outside_course | session.cancelled | attendance.duplicate
     */
    public function handle(array $input): TeacherAttendance
    {
        $data = TeacherAttendanceRequest::validateData($input);

        $teacher = Teacher::query()->findOrFail($data['teacher_id']);
        $course = Course::query()->findOrFail($data['course_id']);
        $date = Dates::parse($data['attended_on']);

        if (! $this->circles->teachesIn($teacher, $course)) {
            throw BusinessRuleViolation::because(
                'teacher.not_in_course',
                'الأستاذ ليس مشرفاً ولا مساعداً في حلقة من هذه الدورة.',
            );
        }

        if (! $course->covers($date)) {
            throw BusinessRuleViolation::because('session.outside_course', 'التاريخ خارج فترة الدورة.');
        }

        return DB::transaction(function () use ($teacher, $course, $date): TeacherAttendance {
            $session = $this->sessions->forManual($course, $date);

            $alreadyRecorded = TeacherAttendance::query()
                ->where('course_session_id', $session->getKey())
                ->where('teacher_id', $teacher->getKey())
                ->exists();

            if ($alreadyRecorded) {
                throw BusinessRuleViolation::because('attendance.duplicate', 'حضور الأستاذ مسجَّل مسبقاً في هذا التاريخ.');
            }

            return TeacherAttendance::query()->create([
                'course_session_id' => $session->getKey(),
                'teacher_id' => $teacher->getKey(),
                'scanned_at' => null,
                'method' => AttendanceMethod::Manual,
            ]);
        });
    }
}
