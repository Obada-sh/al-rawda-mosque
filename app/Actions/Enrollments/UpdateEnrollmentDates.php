<?php

namespace App\Actions\Enrollments;

use App\Exceptions\BusinessRuleViolation;
use App\Http\Requests\Enrollments\EnrollmentDatesRequest;
use App\Models\Enrollment;
use App\Services\Enrollments\EnrollmentCalendar;
use App\Support\Dates;
use Illuminate\Validation\ValidationException;

/**
 * Corrects the enrollment date, and records or clears the date the student
 * left the course. Nothing already recorded may end up outside the period.
 */
final class UpdateEnrollmentDates
{
    public function __construct(private readonly EnrollmentCalendar $calendar) {}

    /**
     * @param  array<string, mixed>  $input
     *
     * @throws ValidationException
     * @throws BusinessRuleViolation enrollment.records_before_enrolled_on | enrollment.records_after_left_on
     */
    public function handle(Enrollment $enrollment, array $input): Enrollment
    {
        $data = EnrollmentDatesRequest::validateData($input);

        $enrolledOn = Dates::day($data['enrolled_on']);
        $leftOn = isset($data['left_on']) ? Dates::day($data['left_on']) : null;

        [$earliest, $latest] = $this->calendar->recordedRange($enrollment);

        if ($earliest !== null && $enrolledOn > $earliest) {
            throw BusinessRuleViolation::because(
                'enrollment.records_before_enrolled_on',
                'للطالب سجلات قبل هذا التاريخ ('.$earliest.').',
            );
        }

        if ($leftOn !== null && $latest !== null && $leftOn < $latest) {
            throw BusinessRuleViolation::because(
                'enrollment.records_after_left_on',
                'للطالب سجلات بعد تاريخ الخروج ('.$latest.').',
            );
        }

        $enrollment->fill(['enrolled_on' => $enrolledOn, 'left_on' => $leftOn])->save();

        return $enrollment;
    }
}
