<?php

namespace App\Services\Statistics;

use App\Enums\SessionStatus;
use App\Models\Circle;
use App\Models\Teacher;
use App\Services\Teachers\TeacherCircles;
use Collator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The attendance of the teachers (supervisors and assistants) in the
 * scope of a report.
 *
 * A teacher is expected at every held session of a course in which they
 * supervise or assist a circle of the scope. The circles keep no history
 * of who taught them when, so a teacher given a circle in the middle of a
 * course is expected from the course's first session.
 */
final class TeacherAttendanceStandings
{
    /**
     * @return Collection<int, TeacherAttendanceStanding> the best attendance first, then by name
     */
    public function for(ReportFilter $filter): Collection
    {
        $circles = Circle::query()
            ->when($filter->courseId !== null, fn ($query) => $query->where('course_id', $filter->courseId))
            ->when($filter->circleId !== null, fn ($query) => $query->whereKey($filter->circleId))
            ->orderBy('name')
            ->get(['id', 'name', 'course_id', 'supervisor_teacher_id', 'assistant_teacher_id']);

        /** @var array<int, array<int, true>> $coursesOf teacher id => course ids */
        $coursesOf = [];
        /** @var array<int, list<Circle>> $circlesOf teacher id => circles */
        $circlesOf = [];

        foreach ($circles as $circle) {
            foreach ([$circle->supervisor_teacher_id, $circle->assistant_teacher_id] as $teacherId) {
                if ($teacherId !== null) {
                    $coursesOf[(int) $teacherId][(int) $circle->course_id] = true;
                    $circlesOf[(int) $teacherId][] = $circle;
                }
            }
        }

        if ($coursesOf === []) {
            return collect();
        }

        $courseIds = $circles->pluck('course_id')->unique()->values()->all();

        $heldQuery = DB::table('course_sessions')
            ->where('status', SessionStatus::Held->value)
            ->whereIn('course_id', $courseIds)
            ->groupBy('course_id')
            ->select('course_id')
            ->selectRaw('COUNT(*) as sessions');
        $filter->applyPeriod($heldQuery, 'session_date');
        $held = $heldQuery->pluck('sessions', 'course_id');

        $attendedQuery = DB::table('teacher_attendances')
            ->join('course_sessions', 'course_sessions.id', '=', 'teacher_attendances.course_session_id')
            ->where('course_sessions.status', SessionStatus::Held->value)
            ->whereIn('course_sessions.course_id', $courseIds)
            ->whereIn('teacher_attendances.teacher_id', array_keys($coursesOf))
            ->groupBy('teacher_attendances.teacher_id', 'course_sessions.course_id')
            ->select('teacher_attendances.teacher_id', 'course_sessions.course_id')
            ->selectRaw('COUNT(*) as attended')
            ->selectRaw('MAX(course_sessions.session_date) as last_day');
        $filter->applyPeriod($attendedQuery, 'course_sessions.session_date');
        $attended = $attendedQuery->get();

        $teachers = Teacher::query()->whereKey(array_keys($coursesOf))->get()->keyBy('id');
        $names = new Collator('ar');

        return collect($coursesOf)
            ->map(function (array $courses, int $teacherId) use ($held, $attended, $teachers, $circlesOf): TeacherAttendanceStanding {
                $own = $attended->filter(fn (object $row): bool => (int) $row->teacher_id === $teacherId
                    && isset($courses[(int) $row->course_id]));

                return new TeacherAttendanceStanding(
                    teacherId: $teacherId,
                    teacherName: (string) $teachers[$teacherId]->full_name,
                    roles: TeacherCircles::roles($teachers[$teacherId], $circlesOf[$teacherId]),
                    sessions: (int) collect(array_keys($courses))->sum(fn (int $courseId): int => (int) ($held[$courseId] ?? 0)),
                    attended: (int) $own->sum('attended'),
                    lastAttendedOn: $own->max('last_day') === null ? null : substr((string) $own->max('last_day'), 0, 10),
                );
            })
            // No session held yet: no rate, after every rate.
            ->sortBy([
                fn (TeacherAttendanceStanding $a, TeacherAttendanceStanding $b): int => ($b->rate() ?? -1) <=> ($a->rate() ?? -1),
                fn (TeacherAttendanceStanding $a, TeacherAttendanceStanding $b): int => (int) $names->compare($a->teacherName, $b->teacherName),
            ])
            ->values();
    }
}
