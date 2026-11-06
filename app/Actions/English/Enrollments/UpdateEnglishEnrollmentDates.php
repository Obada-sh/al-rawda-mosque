<?php

namespace App\Actions\English\Enrollments;

use App\Exceptions\BusinessRuleViolation;
use App\Http\Requests\English\EnglishEnrollmentDatesRequest;
use App\Models\English\EnglishEnrollment;
use App\Models\English\EnglishSession;
use App\Models\English\EnglishTest;
use App\Support\Dates;
use Illuminate\Validation\ValidationException;

/**
 * Corrects the enrollment date in an English course, and records or clears
 * the date the student left it. No attendance and no test result may end
 * up outside the period. Payments have no part in it: a student may pay
 * before the course starts, or after leaving it.
 */
final class UpdateEnglishEnrollmentDates
{
    /**
     * @param  array<string, mixed>  $input
     *
     * @throws ValidationException
     * @throws BusinessRuleViolation english_enrollment.records_before_enrolled_on | english_enrollment.records_after_left_on
     */
    public function handle(EnglishEnrollment $enrollment, array $input): EnglishEnrollment
    {
        $data = EnglishEnrollmentDatesRequest::validateData($input);

        $enrolledOn = Dates::day($data['enrolled_on']);
        $leftOn = isset($data['left_on']) ? Dates::day($data['left_on']) : null;

        [$earliest, $latest] = $this->recordedRange($enrollment);

        if ($earliest !== null && $enrolledOn > $earliest) {
            throw BusinessRuleViolation::because(
                'english_enrollment.records_before_enrolled_on',
                'للطالب سجلات قبل هذا التاريخ ('.Dates::show($earliest).').',
            );
        }

        if ($leftOn !== null && $latest !== null && $leftOn < $latest) {
            throw BusinessRuleViolation::because(
                'english_enrollment.records_after_left_on',
                'للطالب سجلات بعد تاريخ الخروج ('.Dates::show($latest).').',
            );
        }

        $enrollment->fill(['enrolled_on' => $enrolledOn, 'left_on' => $leftOn])->save();

        return $enrollment;
    }

    /**
     * The first and the last day the student has attendance or a test result on.
     *
     * @return array{0: string|null, 1: string|null}
     */
    private function recordedRange(EnglishEnrollment $enrollment): array
    {
        $sessions = EnglishSession::query()->whereIn('id', $enrollment->attendances()->select('english_session_id'));
        $tests = EnglishTest::query()->whereIn('id', $enrollment->testResults()->select('english_test_id'));

        $days = array_map(
            fn (mixed $date): string => substr((string) $date, 0, 10),
            array_filter([
                (clone $sessions)->min('session_date'),
                (clone $sessions)->max('session_date'),
                (clone $tests)->min('held_on'),
                (clone $tests)->max('held_on'),
            ]),
        );

        return $days === [] ? [null, null] : [min($days), max($days)];
    }
}
