<?php

namespace App\Services\Enrollments;

use App\Exceptions\BusinessRuleViolation;
use App\Models\Enrollment;
use Carbon\CarbonInterface;

/**
 * The dates on which something may be recorded for an enrollment.
 *
 * An attendance, recitation or point must fall inside the course dates and
 * inside the period the student was actually enrolled. Keeping this rule in
 * one place keeps attendance rates and per-course totals trustworthy.
 */
final class EnrollmentCalendar
{
    public function assertDateAllowed(Enrollment $enrollment, CarbonInterface $date): void
    {
        $day = $date->toDateString();
        $course = $enrollment->course;

        if ($day < $course->start_date->toDateString()) {
            throw BusinessRuleViolation::because('date.before_course_start', 'التاريخ قبل بداية الدورة.');
        }

        if ($course->end_date !== null && $day > $course->end_date->toDateString()) {
            throw BusinessRuleViolation::because('date.after_course_end', 'التاريخ بعد نهاية الدورة.');
        }

        if ($day < $enrollment->enrolled_on->toDateString()) {
            throw BusinessRuleViolation::because('date.before_enrollment', 'التاريخ قبل تسجيل الطالب في الدورة.');
        }

        if ($enrollment->left_on !== null && $day > $enrollment->left_on->toDateString()) {
            throw BusinessRuleViolation::because('date.after_withdrawal', 'التاريخ بعد خروج الطالب من الدورة.');
        }
    }

    /**
     * The earliest and latest dates that already have something recorded
     * for the enrollment, or nulls when nothing is recorded yet.
     *
     * @return array{0: string|null, 1: string|null} [earliest, latest] as Y-m-d
     */
    public function recordedRange(Enrollment $enrollment): array
    {
        $dates = array_filter([
            $enrollment->recitations()->min('recited_on'),
            $enrollment->recitations()->max('recited_on'),
            $enrollment->pointTransactions()->min('occurred_on'),
            $enrollment->pointTransactions()->max('occurred_on'),
            $enrollment->transfers()->min('transferred_on'),
            $enrollment->transfers()->max('transferred_on'),
            $enrollment->attendances()
                ->join('course_sessions', 'course_sessions.id', '=', 'attendances.course_session_id')
                ->min('course_sessions.session_date'),
            $enrollment->attendances()
                ->join('course_sessions', 'course_sessions.id', '=', 'attendances.course_session_id')
                ->max('course_sessions.session_date'),
            // An activity that gives no points has no line among the points above.
            $enrollment->activityAttendances()
                ->join('activities', 'activities.id', '=', 'activity_attendances.activity_id')
                ->min('activities.activity_date'),
            $enrollment->activityAttendances()
                ->join('activities', 'activities.id', '=', 'activity_attendances.activity_id')
                ->max('activities.activity_date'),
        ]);

        if ($dates === []) {
            return [null, null];
        }

        $days = array_map(fn (mixed $date): string => substr((string) $date, 0, 10), $dates);

        return [min($days), max($days)];
    }
}
