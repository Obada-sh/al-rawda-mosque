<?php

namespace App\Services\English;

use App\Exceptions\BusinessRuleViolation;
use App\Models\English\EnglishEnrollment;
use Carbon\CarbonInterface;

/**
 * The days on which an attendance may be recorded for a student of an
 * English course: inside the course's dates and inside the time the
 * student was in it.
 */
final class EnglishEnrollmentCalendar
{
    /**
     * @throws BusinessRuleViolation date.before_course_start | date.after_course_end | date.before_enrollment | date.after_withdrawal
     */
    public function assertDateAllowed(EnglishEnrollment $enrollment, CarbonInterface $date): void
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
}
