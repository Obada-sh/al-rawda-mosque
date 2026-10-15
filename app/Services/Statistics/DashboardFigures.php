<?php

namespace App\Services\Statistics;

use App\Enums\ExamStatus;
use App\Enums\SessionStatus;
use App\Models\Attendance;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\JuzExam;
use App\Models\Recitation;
use App\Models\Student;
use App\Models\TeacherAttendance;
use App\Support\Dates;

/**
 * The numbers shown on the home page of the panel.
 */
final class DashboardFigures
{
    public function activeStudents(): int
    {
        return Student::query()->active()->count();
    }

    public function incompleteProfiles(): int
    {
        return Student::query()->active()->incompleteProfile()->count();
    }

    public function activeCourses(): int
    {
        return Course::query()->active()->count();
    }

    /**
     * Students currently enrolled in an active course.
     */
    public function enrolledStudents(): int
    {
        return Enrollment::query()
            ->active()
            ->whereHas('course', fn ($query) => $query->active())
            ->distinct()
            ->count('student_id');
    }

    public function attendanceToday(): int
    {
        return Attendance::query()
            ->whereHas('session', fn ($query) => $query
                ->where('session_date', Dates::today()->toDateString())
                ->where('status', SessionStatus::Held->value))
            ->count();
    }

    /**
     * How many teachers came today. A teacher of two running courses has
     * one attendance in each, and is counted once.
     */
    public function teachersPresentToday(): int
    {
        return TeacherAttendance::query()
            ->whereHas('session', fn ($query) => $query
                ->where('session_date', Dates::today()->toDateString())
                ->where('status', SessionStatus::Held->value))
            ->distinct()
            ->count('teacher_id');
    }

    public function pendingExams(): int
    {
        return JuzExam::query()->where('status', ExamStatus::Pending->value)->count();
    }

    /**
     * New pages memorized since the first day of the current month.
     */
    public function newPagesThisMonth(): int
    {
        return (int) Recitation::query()
            ->whereBetween('recited_on', [
                Dates::today()->startOfMonth()->toDateString(),
                Dates::today()->toDateString(),
            ])
            ->sum('new_pages_count');
    }
}
