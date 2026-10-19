<?php

namespace App\Actions\Documents;

use App\Http\Requests\Documents\MosqueSheetRequest;
use App\Models\Circle;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Student;
use App\Services\Statistics\ReportFilter;
use App\Services\Statistics\StudentStanding;
use App\Services\Statistics\StudentStandings;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Builds the sheet of the mosque's students, without choosing a circle:
 *
 * - every course (SheetOptions::ALL_COURSES): every student in the
 *   system, also those in no course yet, with everything they did since
 *   they joined; their circle is the one they are in now (in a running
 *   course). Archived students only when asked.
 * - one course (SheetOptions::THIS_COURSE): the students of that course
 *   who did not leave it, from all its circles, with what they did in it.
 *
 * One table by name; the columns are those the admin chose. The records
 * come from SheetRecords, like those of the circles' sheets.
 */
final class PrepareMosqueSheet
{
    public function __construct(
        private readonly StudentStandings $standings,
        private readonly SheetRecords $records,
    ) {}

    /**
     * @param  array<string, mixed>  $input  history, course_id (one course), archived (every course), and the columns of SheetOptions
     *
     * @throws ValidationException
     */
    public function handle(array $input): MosqueSheet
    {
        $data = MosqueSheetRequest::validateData($input);
        $options = SheetOptions::from($data);

        if (! $options->allCourses) {
            $course = Course::query()->findOrFail($data['course_id']);

            return new MosqueSheet($options, $course, false, $this->ofCourse($course));
        }

        $withArchived = (bool) ($data['archived'] ?? false);

        return new MosqueSheet($options, null, $withArchived, $this->ofEveryCourse($withArchived));
    }

    /**
     * @return list<SheetLine>
     */
    private function ofCourse(Course $course): array
    {
        $standings = $this->standings
            ->for(new ReportFilter(courseId: $course->getKey()))
            ->reject(fn (StudentStanding $standing): bool => $standing->hasLeft())
            ->values();

        $students = Student::query()->whereKey($standings->pluck('studentId')->unique()->all())->get()->keyBy('id');
        $circles = Circle::query()->whereKey($standings->pluck('circleId')->unique()->all())->pluck('name', 'id');
        $records = $this->records->inCourse($standings);

        return $standings
            ->map(fn (StudentStanding $standing): SheetLine => SheetLine::of(
                $students->get($standing->studentId),
                $records[$standing->enrollmentId],
                circle: $circles->get($standing->circleId),
            ))
            ->all();
    }

    /**
     * @return list<SheetLine>
     */
    private function ofEveryCourse(bool $withArchived): array
    {
        $students = Student::query()
            ->when(! $withArchived, fn ($query) => $query->active())
            ->orderBy('full_name')
            ->orderBy('id')
            ->get();

        $records = $this->records->sinceJoining($students);
        $circles = $this->currentCircles($students);

        return $students
            ->map(fn (Student $student): SheetLine => SheetLine::of(
                $student,
                $records[$student->getKey()],
                circle: $circles[$student->getKey()] ?? null,
            ))
            ->values()
            ->all();
    }

    /**
     * The circles the students are in now: those of their enrollments
     * they did not leave, in the courses that are running.
     *
     * @param  Collection<int, Student>  $students
     * @return array<int, string> by student id; "حلقة أ، حلقة ب" for a student of two running courses
     */
    private function currentCircles(Collection $students): array
    {
        return Enrollment::query()
            ->active()
            ->whereIn('student_id', $students->modelKeys())
            ->whereHas('course', fn ($query) => $query->active())
            ->with('circle')
            ->orderBy('id')
            ->get()
            ->groupBy('student_id')
            ->map(fn (Collection $enrollments): string => $enrollments
                ->map(fn (Enrollment $enrollment): string => $enrollment->circle->name)
                ->unique()
                ->implode('، '))
            ->all();
    }
}
