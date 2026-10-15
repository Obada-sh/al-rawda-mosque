<?php

namespace App\Services\Attendance;

use App\Enums\SessionStatus;
use App\Exceptions\BusinessRuleViolation;
use App\Models\Course;
use App\Models\CourseSession;
use Carbon\CarbonInterface;

/**
 * Finds or opens the session (class day) an attendance belongs to.
 */
final class SessionResolver
{
    /**
     * For a card scan: the session exists already, or the date is one of
     * the course's weekly attendance days.
     *
     * @throws BusinessRuleViolation session.cancelled | session.not_scheduled
     */
    public function forScan(Course $course, CarbonInterface $date): CourseSession
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
     * For manual attendance: the admin may record any date, so a missing
     * session is opened even when the date is not a weekly attendance day.
     *
     * @throws BusinessRuleViolation session.cancelled
     */
    public function forManual(Course $course, CarbonInterface $date): CourseSession
    {
        $session = $this->find($course, $date);

        if ($session !== null) {
            $this->assertNotCancelled($session);

            return $session;
        }

        return $this->open($course, $date);
    }

    public function find(Course $course, CarbonInterface $date): ?CourseSession
    {
        return CourseSession::query()
            ->where('course_id', $course->getKey())
            ->where('session_date', $date->toDateString())
            ->first();
    }

    /**
     * createOrFirst, not create: when two cards are scanned at the same
     * moment on the first scan of the day, only one session may be opened.
     *
     * @throws BusinessRuleViolation session.cancelled
     */
    private function open(Course $course, CarbonInterface $date): CourseSession
    {
        $session = CourseSession::query()->createOrFirst(
            ['course_id' => $course->getKey(), 'session_date' => $date->toDateString()],
            ['status' => SessionStatus::Held],
        );

        $this->assertNotCancelled($session);

        return $session;
    }

    private function assertNotCancelled(CourseSession $session): void
    {
        if ($session->isCancelled()) {
            throw BusinessRuleViolation::because('session.cancelled', 'جلسة هذا اليوم ملغاة. أعد فتحها أولاً.');
        }
    }
}
