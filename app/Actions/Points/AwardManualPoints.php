<?php

namespace App\Actions\Points;

use App\Exceptions\BusinessRuleViolation;
use App\Http\Requests\Points\ManualPointsRequest;
use App\Models\Enrollment;
use App\Models\PointTransaction;
use App\Services\Enrollments\EnrollmentCalendar;
use App\Services\Points\PointsLedger;
use App\Support\Dates;
use Illuminate\Validation\ValidationException;

/**
 * Adds points by hand (a reward) or deducts them (a penalty), with a
 * reason. The points go to the student's enrollment in a specific course.
 */
final class AwardManualPoints
{
    public function __construct(
        private readonly EnrollmentCalendar $calendar,
        private readonly PointsLedger $points,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     *
     * @throws ValidationException
     * @throws BusinessRuleViolation date.*
     */
    public function handle(array $input): PointTransaction
    {
        $data = ManualPointsRequest::validateData($input);

        $enrollment = Enrollment::query()->with('course')->findOrFail($data['enrollment_id']);
        $occurredOn = Dates::parse($data['occurred_on']);

        $this->calendar->assertDateAllowed($enrollment, $occurredOn);

        return $this->points->addManual($enrollment, (int) $data['points'], $data['reason'], $occurredOn);
    }
}
