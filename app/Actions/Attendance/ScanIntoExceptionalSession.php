<?php

namespace App\Actions\Attendance;

use App\Enums\ScanStatus;
use App\Exceptions\BusinessRuleViolation;
use App\Services\Attendance\SessionResolver;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * For a card that was refused because its day (today, or the earlier day
 * the desk was set to) is not a class day: opens an exceptional session
 * on that day in the card's course (the student's course, or the course
 * of the teacher's card), then records the attendance of the card.
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
     * @param  CarbonInterface|null  $at  when the card was read (now by default)
     * @param  CarbonInterface|null  $day  the day of the session (the day of $at by default)
     *
     * @throws BusinessRuleViolation session.outside_course | session.exists
     */
    public function handle(?string $cardCode, ?CarbonInterface $at = null, ?CarbonInterface $day = null): ScanResult
    {
        $at ??= now();
        $day ??= $at;

        return DB::transaction(function () use ($cardCode, $at, $day): ScanResult {
            $refusal = $this->scanCard->handle($cardCode, $at, $day);

            if ($refusal->status !== ScanStatus::NotScheduledDay) {
                return $refusal;
            }

            $course = $refusal->enrollment?->course ?? $refusal->teacherCourse;

            if ($course !== null && $this->sessions->find($course, $day) === null) {
                $this->openSession->handle($course, ['session_date' => $day->toDateString()]);
            }

            return $this->scanCard->handle($cardCode, $at, $day);
        });
    }
}
