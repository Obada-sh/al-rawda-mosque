<?php

namespace App\Actions\Points;

use App\Exceptions\BusinessRuleViolation;
use App\Http\Requests\Points\ManualPointsRequest;
use App\Models\PointTransaction;
use App\Services\Enrollments\EnrollmentCalendar;
use App\Support\Dates;
use Illuminate\Validation\ValidationException;

/**
 * Corrects manual points. Automatic points cannot be edited here: they
 * follow the attendance, recitation or activity that earned them.
 */
final class UpdateManualPoints
{
    public function __construct(private readonly EnrollmentCalendar $calendar) {}

    /**
     * @param  array<string, mixed>  $input
     *
     * @throws ValidationException
     * @throws BusinessRuleViolation points.not_manual | date.*
     */
    public function handle(PointTransaction $transaction, array $input): PointTransaction
    {
        if (! $transaction->isManual()) {
            throw BusinessRuleViolation::because(
                'points.not_manual',
                'النقاط التلقائية تُعدَّل من الحضور أو التسميع أو النشاط الذي أضافها.',
            );
        }

        $data = ManualPointsRequest::validateData($input, ['updating' => true]);

        $enrollment = $transaction->enrollment()->with('course')->firstOrFail();
        $occurredOn = Dates::parse($data['occurred_on']);

        $this->calendar->assertDateAllowed($enrollment, $occurredOn);

        $transaction->fill([
            'points' => (int) $data['points'],
            'reason' => $data['reason'],
            'occurred_on' => $occurredOn->toDateString(),
        ])->save();

        return $transaction;
    }
}
