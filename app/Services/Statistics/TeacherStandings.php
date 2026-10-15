<?php

namespace App\Services\Statistics;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * How many pages each teacher heard in the scope of a report.
 */
final class TeacherStandings
{
    /**
     * @return Collection<int, TeacherStanding> the teacher who heard the most pages first
     */
    public function for(ReportFilter $filter): Collection
    {
        $query = DB::table('recitations')
            ->join('enrollments', 'enrollments.id', '=', 'recitations.enrollment_id')
            ->join('teachers', 'teachers.id', '=', 'recitations.teacher_id')
            ->groupBy('teachers.id', 'teachers.full_name')
            ->select('teachers.id', 'teachers.full_name')
            ->selectRaw('COUNT(*) as recitations')
            ->selectRaw('SUM(recitations.pages_count) as pages')
            ->selectRaw('SUM(recitations.new_pages_count) as new_pages')
            ->selectRaw('COUNT(DISTINCT enrollments.student_id) as students')
            ->orderByDesc('pages')
            ->orderBy('teachers.full_name');

        $filter->applyEnrollments($query);
        $filter->applyPeriod($query, 'recitations.recited_on');

        return $query->get()->map(fn (object $row): TeacherStanding => new TeacherStanding(
            teacherId: (int) $row->id,
            teacherName: (string) $row->full_name,
            recitations: (int) $row->recitations,
            pages: (int) $row->pages,
            newPages: (int) $row->new_pages,
            students: (int) $row->students,
        ));
    }
}
