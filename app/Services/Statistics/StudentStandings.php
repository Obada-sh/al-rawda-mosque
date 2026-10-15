<?php

namespace App\Services\Statistics;

use App\Enums\SessionStatus;
use App\Models\Enrollment;
use App\Models\Student;
use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The figures of every student in the scope of a report: attendance,
 * pages and points, one row per student in a course.
 *
 * Every other report (circles, courses, totals, rankings, the slides) is
 * built from these rows, so their numbers always add up.
 */
final class StudentStandings
{
    /**
     * @return Collection<int, StudentStanding> ordered by student name
     */
    public function for(ReportFilter $filter): Collection
    {
        $query = Enrollment::query();

        $filter->applyEnrollments($query);

        return $this->standings($query, $filter);
    }

    /**
     * What one student did in each of their courses, since the beginning.
     *
     * @return Collection<int, StudentStanding> keyed by enrollment id
     */
    public function forStudent(Student $student): Collection
    {
        return $this->standings(
            Enrollment::query()->where('enrollments.student_id', $student->getKey()),
            new ReportFilter,
        )->keyBy('enrollmentId');
    }

    /**
     * @param  EloquentBuilder<Enrollment>  $enrollments  the enrollments to report on
     * @return Collection<int, StudentStanding>
     */
    private function standings(EloquentBuilder $enrollments, ReportFilter $filter): Collection
    {
        return $enrollments
            ->with(['student', 'course', 'circle'])
            ->addSelect([
                'report_new_pages' => $this->sumOf('recitations', 'new_pages_count', 'recited_on', $filter),
                'report_recited_pages' => $this->sumOf('recitations', 'pages_count', 'recited_on', $filter),
                'report_points' => $this->sumOf('point_transactions', 'points', 'occurred_on', $filter),
                'report_attended' => $this->attendedSessions($filter),
                'report_sessions' => $this->heldSessions($filter),
            ])
            ->orderBy(Student::query()->select('full_name')->whereColumn('students.id', 'enrollments.student_id'))
            ->orderBy('enrollments.id')
            ->get()
            ->map(fn (Enrollment $enrollment): StudentStanding => new StudentStanding(
                enrollmentId: $enrollment->getKey(),
                studentId: $enrollment->student_id,
                studentName: $enrollment->student->full_name,
                courseId: $enrollment->course_id,
                courseName: $enrollment->course->name,
                circleId: $enrollment->circle_id,
                circleName: $enrollment->circle->name,
                leftOn: $enrollment->left_on?->toDateString(),
                newPages: (int) $enrollment->getAttribute('report_new_pages'),
                recitedPages: (int) $enrollment->getAttribute('report_recited_pages'),
                points: (int) $enrollment->getAttribute('report_points'),
                attended: (int) $enrollment->getAttribute('report_attended'),
                sessions: (int) $enrollment->getAttribute('report_sessions'),
            ))
            ->values();
    }

    /**
     * The total of a column of the student's records inside the period.
     */
    private function sumOf(string $table, string $column, string $dateColumn, ReportFilter $filter): Builder
    {
        $query = DB::table($table)
            ->selectRaw("COALESCE(SUM({$table}.{$column}), 0)")
            ->whereColumn("{$table}.enrollment_id", 'enrollments.id');

        $filter->applyPeriod($query, "{$table}.{$dateColumn}");

        return $query;
    }

    /**
     * The sessions of the period the student attended.
     */
    private function attendedSessions(ReportFilter $filter): Builder
    {
        $query = DB::table('attendances')
            ->join('course_sessions', 'course_sessions.id', '=', 'attendances.course_session_id')
            ->selectRaw('COUNT(*)')
            ->whereColumn('attendances.enrollment_id', 'enrollments.id')
            ->where('course_sessions.status', SessionStatus::Held->value);

        $filter->applyPeriod($query, 'course_sessions.session_date');

        return $query;
    }

    /**
     * The sessions of the period that were held while the student was in
     * the course. Absence is not stored: a student was absent from a held
     * session they have no attendance in.
     */
    private function heldSessions(ReportFilter $filter): Builder
    {
        $query = DB::table('course_sessions')
            ->selectRaw('COUNT(*)')
            ->whereColumn('course_sessions.course_id', 'enrollments.course_id')
            ->where('course_sessions.status', SessionStatus::Held->value)
            ->whereColumn('course_sessions.session_date', '>=', 'enrollments.enrolled_on')
            ->where(fn (Builder $query) => $query
                ->whereNull('enrollments.left_on')
                ->orWhereColumn('course_sessions.session_date', '<=', 'enrollments.left_on'));

        $filter->applyPeriod($query, 'course_sessions.session_date');

        return $query;
    }
}
