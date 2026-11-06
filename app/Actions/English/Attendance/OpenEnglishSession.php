<?php

namespace App\Actions\English\Attendance;

use App\Enums\SessionStatus;
use App\Exceptions\BusinessRuleViolation;
use App\Http\Requests\Courses\SessionRequest;
use App\Models\English\EnglishCourse;
use App\Models\English\EnglishSession;
use App\Services\English\EnglishSessionResolver;
use App\Support\Dates;
use Illuminate\Validation\ValidationException;

/**
 * Opens a class day of an English course outside its weekly days, or
 * reopens a cancelled one.
 */
final class OpenEnglishSession
{
    public function __construct(private readonly EnglishSessionResolver $sessions) {}

    /**
     * @param  array<string, mixed>  $input  session_date
     *
     * @throws ValidationException
     * @throws BusinessRuleViolation session.outside_course | session.exists
     */
    public function handle(EnglishCourse $course, array $input): EnglishSession
    {
        $data = SessionRequest::validateData($input);
        $date = Dates::parse($data['session_date']);

        if (! $course->covers($date)) {
            throw BusinessRuleViolation::because('session.outside_course', 'التاريخ خارج فترة الدورة.');
        }

        $existing = $this->sessions->find($course, $date);

        if ($existing === null) {
            return EnglishSession::query()->create([
                'english_course_id' => $course->getKey(),
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
