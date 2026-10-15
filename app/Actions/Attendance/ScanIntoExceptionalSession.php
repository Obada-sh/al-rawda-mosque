<?php

namespace App\Actions\Attendance;

use App\Enums\ScanStatus;
use App\Exceptions\BusinessRuleViolation;
use App\Services\Attendance\SessionResolver;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * For a card that was refused because today is not a class day: opens an
 * exceptional session for today in the card's course (the student's
 * course, or the course of the teacher's card), then records the
 * attendance of the card.
 *
 * A card refused for any other reason is still refused, and nothing is
 * opened.
 */
final class ScanIntoExceptionalSession
{
    public function __construct(
        private readonly ScanCard $scanCard,
        private readonly OpenSession $openSession,
        private readonly SessionResolver $sessions,
    ) {}

    /**
     * @throws BusinessRuleViolation session.outside_course | session.exists
     */
    public function handle(?string $cardCode, ?CarbonInterface $at = null): ScanResult
    {
        $at ??= now();

        return DB::transaction(function () use ($cardCode, $at): ScanResult {
            $refusal = $this->scanCard->handle($cardCode, $at);

            if ($refusal->status !== ScanStatus::NotScheduledDay) {
                return $refusal;
            }

            $course = $refusal->enrollment?->course ?? $refusal->teacherCourse;

            if ($course !== null && $this->sessions->find($course, $at) === null) {
                $this->openSession->handle($course, ['session_date' => $at->toDateString()]);
            }

            return $this->scanCard->handle($cardCode, $at);
        });
    }
}
