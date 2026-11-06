<?php

namespace App\Services\English;

use App\Enums\Section;
use App\Enums\SessionStatus;
use App\Models\English\EnglishAttendance;
use App\Models\English\EnglishCourse;
use App\Models\English\EnglishEnrollment;
use App\Models\English\EnglishPayment;
use App\Models\Student;
use App\Support\Dates;

/**
 * The numbers shown on the home page of the English section. Nothing of
 * the mosque is counted here.
 */
final class EnglishDashboardFigures
{
    public function students(): int
    {
        return Student::query()->inEnglish()->count();
    }

    public function incompleteProfiles(): int
    {
        return Student::query()->inEnglish()->incompleteProfile(Section::English)->count();
    }

    public function activeCourses(): int
    {
        return EnglishCourse::query()->active()->count();
    }

    /**
     * Students in a running course (once each, whatever the number of courses).
     */
    public function enrolledStudents(): int
    {
        return EnglishEnrollment::query()
            ->whereNull('left_on')
            ->whereHas('course', fn ($query) => $query->active())
            ->distinct()
            ->count('student_id');
    }

    public function attendanceToday(): int
    {
        return EnglishAttendance::query()
            ->whereHas('session', fn ($query) => $query
                ->where('session_date', Dates::today()->toDateString())
                ->where('status', SessionStatus::Held->value))
            ->count();
    }

    /**
     * What the students paid this month, in Syrian pounds.
     */
    public function paidThisMonth(): int
    {
        $today = Dates::today();

        return (int) EnglishPayment::query()
            ->whereBetween('paid_on', [$today->startOfMonth()->toDateString(), $today->endOfMonth()->toDateString()])
            ->sum('amount');
    }
}
