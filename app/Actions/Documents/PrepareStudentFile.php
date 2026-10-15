<?php

namespace App\Actions\Documents;

use App\Enums\SessionStatus;
use App\Http\Requests\Documents\StudentFileRequest;
use App\Models\Attendance;
use App\Models\CourseSession;
use App\Models\Enrollment;
use App\Models\Recitation;
use App\Models\Student;
use App\Services\Statistics\StudentStandings;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

/**
 * Builds a student's file: their details, and for each of their courses
 * (or the one chosen) the figures, every page recited and every session
 * attended or missed.
 *
 * The figures come from the statistics, and the sessions listed are the
 * ones the statistics count, so the file and the reports always agree.
 */
final class PrepareStudentFile
{
    public function __construct(private readonly StudentStandings $standings) {}

    /**
     * @param  array<string, mixed>  $input  course_id (optional): one course of the student
     *
     * @throws ValidationException
     */
    public function handle(Student $student, array $input): StudentFile
    {
        $data = StudentFileRequest::validateData($input, ['student_id' => $student->getKey()]);

        $standings = $this->standings->forStudent($student);

        $enrollments = Enrollment::query()
            ->with(['course', 'circle'])
            ->where('student_id', $student->getKey())
            ->when($data['course_id'] ?? null, fn (Builder $query, mixed $courseId) => $query->where('course_id', $courseId))
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
