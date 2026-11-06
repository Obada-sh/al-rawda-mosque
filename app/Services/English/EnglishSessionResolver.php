<?php

namespace App\Services\English;

use App\Enums\SessionStatus;
use App\Exceptions\BusinessRuleViolation;
use App\Models\English\EnglishCourse;
use App\Models\English\EnglishSession;
use Carbon\CarbonInterface;

/**
 * Finds or opens the class day an English attendance belongs to: the same
 * rules as the mosque's sessions, on the English section's own tables.
 */
final class EnglishSessionResolver
{
    /**
     * For a card scan: the session exists already, or the date is one of
     * the course's weekly class days.
     *
     * @throws BusinessRuleViolation session.cancelled | session.not_scheduled
     */
    public function forScan(EnglishCourse $course, CarbonInterface $date): EnglishSession
    {
        $session = $this->find($course, $date);

        if ($session !== null) {
            $this->assertNotCancelled($session);

            return $session;
        }

        if (! $course->isScheduledOn($date)) {
            throw BusinessRuleViolation::because('session.not_scheduled', 'اليوم ليس يوم دوام لهذه الدورة.');
        }

        return $this->open($course, $date);
    }

    /**
     * For manual attendance: any date may be recorded, so a missing session
     * is opened even when the date is not a weekly class day.
     *
     * @throws BusinessRuleViolation session.cancelled
     */
    public function forManual(EnglishCourse $course, CarbonInterface $date): EnglishSession
    {
        $session = $this->find($course, $date);

        if ($session !== null) {
            $this->assertNotCancelled($session);

            return $session;
        }

        return $this->open($course, $date);
    }

    public function find(EnglishCourse $course, CarbonInterface $date): ?EnglishSession
    {
        return EnglishSession::query()
            ->where('english_course_id', $course->getKey())
            ->where('session_date', $date->toDateString())
            ->first();
    }

    /**
     * createOrFirst, not create: two cards scanned at the same moment on
     * the first scan of the day open one session only.
     *
     * @throws BusinessRuleViolation session.cancelled
     */
    private function open(EnglishCourse $course, CarbonInterface $date): EnglishSession
    {
        $session = EnglishSession::query()->createOrFirst(
            ['english_course_id' => $course->getKey(), 'session_date' => $date->toDateString()],
            ['status' => SessionStatus::Held],
        );

        $this->assertNotCancelled($session);

        return $session;
    }

    private function assertNotCancelled(EnglishSession $session): void
    {
        if ($session->isCancelled()) {
            throw BusinessRuleViolation::because('session.cancelled', 'جلسة هذا اليوم ملغاة. أعد فتحها أولاً.');
        }
    }
}
