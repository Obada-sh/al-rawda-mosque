<?php

namespace App\Actions\English\Attendance;

use App\Enums\ScanStatus;
use App\Exceptions\BusinessRuleViolation;
use App\Services\English\EnglishSessionResolver;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * For a card refused because its day is not a class day of its course:
 * opens an exceptional session on that day, then records the attendance.
 * A card refused for any other reason is still refused, and nothing is
 * opened.
 */
final class ScanEnglishIntoExceptionalSession
{
    public function __construct(
        private readonly RecordEnglishScan $scan,
        private readonly OpenEnglishSession $openSession,
        private readonly EnglishSessionResolver $sessions,
    ) {}

    /**
     * @throws BusinessRuleViolation session.outside_course | session.exists
     */
    public function handle(?string $cardCode, ?CarbonInterface $at = null, ?CarbonInterface $day = null): EnglishScanResult
    {
        $at ??= now();
        $day ??= $at;

        return DB::transaction(function () use ($cardCode, $at, $day): EnglishScanResult {
            $refusal = $this->scan->handle($cardCode, $at, $day);

            if ($refusal->status !== ScanStatus::NotScheduledDay) {
                return $refusal;
            }

            $course = $refusal->enrollment?->course;

            if ($course !== null && $this->sessions->find($course, $day) === null) {
                $this->openSession->handle($course, ['session_date' => $day->toDateString()]);
            }

            return $this->scan->handle($cardCode, $at, $day);
        });
    }
}
