<?php

namespace App\Actions\Attendance;

use App\Enums\SessionStatus;
use App\Exceptions\BusinessRuleViolation;
use App\Http\Requests\Courses\SessionRequest;
use App\Models\Course;
use App\Models\CourseSession;
use App\Services\Attendance\SessionResolver;
use App\Support\Dates;
use Illuminate\Validation\ValidationException;

/**
 * Opens a session on a date outside the weekly timetable, or reopens a
 * cancelled one.
 */
final class OpenSession
{
    public function __construct(private readonly SessionResolver $sessions) {}

    /**
     * @param  array<string, mixed>  $input
     *
     * @throws ValidationException
     * @throws BusinessRuleViolation session.outside_course | session.exists
     */
    public function handle(Course $course, array $input): CourseSession
    {
        $data = SessionRequest::validateData($input);
        $date = Dates::parse($data['session_date']);

        if (! $course->covers($date)) {
            throw BusinessRuleViolation::because('session.outside_course', 'التاريخ خارج فترة الدورة.');
        }

        $existing = $this->sessions->find($course, $date);

        if ($existing === null) {
            return CourseSession::query()->create([
                'course_id' => $course->getKey(),
                'session_date' => $date->toDateString(),
                'status' => SessionStatus::Held,
            ]);
        }

        if (! $existing->isCancelled()) {
            throw BusinessRuleViolation::because('session.exists', 'توجد جلسة في هذا التاريخ.');
        }

        $existing->fill(['status' => SessionStatus::Held])->save();

        return $existing;
    }
}
