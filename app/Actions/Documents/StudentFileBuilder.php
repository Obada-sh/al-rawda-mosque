<?php

namespace App\Actions\Documents;

use App\Enums\SessionStatus;
use App\Models\Attendance;
use App\Models\CourseSession;
use App\Models\Enrollment;
use App\Models\Recitation;
use App\Models\Student;
use App\Services\Statistics\StudentStanding;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Puts a student's file together: their details, and for each of their
 * courses (or the one asked for) the figures, every page recited and every
 * session attended or missed. Used for one file (PrepareStudentFile) and
 * for many (PrepareStudentFiles).
 *
 * The figures come from the statistics, and the sessions listed are the
 * ones the statistics count, so the file and the reports always agree.
 */
final class StudentFileBuilder
{
    /**
     * @param  int|null  $courseId  only this course of the student; null for every course
     * @param  Collection<int, StudentStanding>  $standings  the student's standings (at least), keyed by enrollment id
     */
    public function build(Student $student, ?int $courseId, Collection $standings): StudentFile
    {
        $enrollments = Enrollment::query()
            ->with(['course', 'circle'])
            ->where('student_id', $student->getKey())
            ->when($courseId, fn (Builder $query, int $courseId) => $query->where('course_id', $courseId))
            ->get()
            ->sortBy([
                fn (Enrollment $a, Enrollment $b): int => $b->course->start_date <=> $a->course->start_date,
                fn (Enrollment $a, Enrollment $b): int => $b->getKey() <=> $a->getKey(),
            ])
            ->values();

        return new StudentFile(
            student: $student->loadMissing('tajweedLevel'),
            courses: $enrollments
                ->map(fn (Enrollment $enrollment): StudentCourseFile => new StudentCourseFile(
                    enrollment: $enrollment,
                    standing: $standings->get($enrollment->getKey()),
                    recitations: $this->recitations($enrollment),
                    days: $this->days($enrollment),
                ))
                ->all(),
        );
    }

    /**
     * @return list<Recitation>
     */
    private function recitations(Enrollment $enrollment): array
    {
        return Recitation::query()
            ->with('teacher')
            ->where('enrollment_id', $enrollment->getKey())
            ->orderBy('recited_on')
            ->orderBy('id')
            ->get()
            ->all();
    }

    /**
     * The held sessions of the course while the student was in it, and any
     * other held session they attended.
     *
     * @return list<AttendanceDay>
     */
    private function days(Enrollment $enrollment): array
    {
        $attendances = Attendance::query()
            ->where('enrollment_id', $enrollment->getKey())
            ->get()
            ->keyBy('course_session_id');

        return CourseSession::query()
            ->where('course_id', $enrollment->course_id)
            ->where('status', SessionStatus::Held->value)
            ->where(fn (Builder $query) => $query
                ->where(fn (Builder $query) => $query
                    ->where('session_date', '>=', $enrollment->enrolled_on->toDateString())
                    ->when($enrollment->left_on, fn (Builder $query, $leftOn) => $query->where('session_date', '<=', $leftOn->toDateString())))
                ->orWhereIn('id', $attendances->keys()->all()))
            ->orderBy('session_date')
            ->orderBy('id')
            ->get()
            ->map(fn (CourseSession $session): AttendanceDay => new AttendanceDay($session, $attendances->get($session->getKey())))
            ->all();
    }
}
