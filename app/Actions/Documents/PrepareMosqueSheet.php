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
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Builds the sheet of the mosque's students, without choosing a circle:
 *
 * - every course (SheetOptions::ALL_COURSES): every student of the
 *   mosque, also those in no course yet, with everything they did since
 *   they joined; their circle is the one they are in now (in a running
 *   course). Archived students only when asked.
 * - one course (SheetOptions::THIS_COURSE): the students of that course
 *   who did not leave it, from all its circles, with what they did in it.
 *
 * One table by name, each student with their circle; or, when asked
 * (MosqueSheet::BY_CIRCLE), circle by circle: the circles that have
 * students, each with its students by name, and last the students who are
 * in no circle now. A student in two running courses at once is under
 * each of their two circles. The columns are those the admin chose; the
 * records come from SheetRecords, like those of the circles' sheets.
 */
final class PrepareMosqueSheet
{
    private const CIRCLE_RELATIONS = ['course', 'supervisor', 'assistant'];

    public function __construct(
        private readonly StudentStandings $standings,
        private readonly SheetRecords $records,
    ) {}

    /**
     * @param  array<string, mixed>  $input  history, course_id (one course), archived (every course), order, and the columns of SheetOptions
     *
     * @throws ValidationException
     */
    public function handle(array $input): MosqueSheet
    {
        $data = MosqueSheetRequest::validateData($input);
        $options = SheetOptions::from($data);
        $byCircle = ($data['order'] ?? MosqueSheet::BY_NAME) === MosqueSheet::BY_CIRCLE;

        if (! $options->allCourses) {
            $course = Course::query()->findOrFail($data['course_id']);
            [$placed, $circles] = $this->ofCourse($course);

            return $this->sheet($options, $course, false, $placed, $circles, $byCircle);
        }

        $withArchived = (bool) ($data['archived'] ?? false);
        [$placed, $circles] = $this->ofEveryCourse($withArchived);

        return $this->sheet($options, null, $withArchived, $placed, $circles, $byCircle);
    }

    /**
     * @param  list<array{SheetLine, list<int>}>  $placed  each line with the circles its student is in
     * @param  Collection<int, Circle>  $circles  in the order of the sheet
     */
    private function sheet(SheetOptions $options, ?Course $course, bool $withArchived, array $placed, Collection $circles, bool $byCircle): MosqueSheet
    {
        $lines = array_map(fn (array $place): SheetLine => $place[0], $placed);

        return new MosqueSheet(
            options: $options,
            course: $course,
            withArchived: $withArchived,
            lines: $lines,
            byCircle: $byCircle,
            groups: $byCircle ? $this->groups($placed, $circles) : [],
        );
    }

    /**
     * The circles that have students, in their order, then the students in none.
     *
     * @param  list<array{SheetLine, list<int>}>  $placed
     * @param  Collection<int, Circle>  $circles
     * @return list<MosqueSheetGroup>
     */
    private function groups(array $placed, Collection $circles): array
    {
        $linesIn = fn (callable $belongs): array => array_values(array_map(
            fn (array $place): SheetLine => $place[0],
            array_filter($placed, fn (array $place): bool => $belongs($place[1])),
        ));

        $groups = $circles
            ->map(fn (Circle $circle): MosqueSheetGroup => new MosqueSheetGroup(
                $circle,
                $linesIn(fn (array $circleIds): bool => in_array((int) $circle->getKey(), $circleIds, true)),
            ))
            ->reject(fn (MosqueSheetGroup $group): bool => $group->isEmpty())
            ->values()
            ->all();

        $withoutCircle = new MosqueSheetGroup(null, $linesIn(fn (array $circleIds): bool => $circleIds === []));

        return $withoutCircle->isEmpty() ? $groups : [...$groups, $withoutCircle];
    }

    /**
     * @return array{list<array{SheetLine, list<int>}>, Collection<int, Circle>}
     */
    private function ofCourse(Course $course): array
    {
        $standings = $this->standings
            ->for(new ReportFilter(courseId: $course->getKey()))
            ->reject(fn (StudentStanding $standing): bool => $standing->hasLeft())
            ->values();

        $students = Student::query()->whereKey($standings->pluck('studentId')->unique()->all())->get()->keyBy('id');
        $circles = Circle::query()
            ->with(self::CIRCLE_RELATIONS)
            ->whereKey($standings->pluck('circleId')->unique()->all())
            ->orderBy('name')
            ->orderBy('id')
            ->get();
        $names = $circles->pluck('name', 'id');
        $records = $this->records->inCourse($standings);

        $placed = $standings
            ->map(fn (StudentStanding $standing): array => [
                SheetLine::of(
                    $students->get($standing->studentId),
                    $records[$standing->enrollmentId],
                    circle: $names->get($standing->circleId),
                ),
                [$standing->circleId],
            ])
            ->all();

        return [$placed, $circles];
    }

    /**
     * @return array{list<array{SheetLine, list<int>}>, Collection<int, Circle>}
     */
    private function ofEveryCourse(bool $withArchived): array
    {
        $students = Student::query()
            ->inMosque()
            ->when(! $withArchived, fn (Builder $query) => $query->active())
            ->orderBy('full_name')
            ->orderBy('id')
            ->get();

        $records = $this->records->sinceJoining($students);
        $current = $this->currentCircleIds($students);

        // The circles of the running courses: the oldest course first, then by name.
        $circles = Circle::query()
            ->with(self::CIRCLE_RELATIONS)
            ->whereKey($current->flatten()->unique()->all())
            ->orderBy(Course::query()->select('start_date')->whereColumn('courses.id', 'circles.course_id'))
            ->orderBy('course_id')
            ->orderBy('name')
            ->orderBy('id')
            ->get();
        $names = $circles->pluck('name', 'id');

        $placed = $students
            ->map(function (Student $student) use ($records, $current, $names): array {
                /** @var list<int> $circleIds */
                $circleIds = $current->get($student->getKey(), []);

                return [
                    SheetLine::of(
                        $student,
                        $records[$student->getKey()],
                        circle: $circleIds === [] ? null : collect($circleIds)->map(fn (int $id): string => (string) $names->get($id))->unique()->implode('، '),
                    ),
                    $circleIds,
                ];
            })
            ->values()
            ->all();

        return [$placed, $circles];
    }

    /**
     * The circles the students are in now: those of their enrollments
     * they did not leave, in the courses that are running.
     *
     * @param  Collection<int, Student>  $students
     * @return Collection<int, list<int>> circle ids by student id, in the order of the enrollments; a student of two running courses has two
     */
    private function currentCircleIds(Collection $students): Collection
    {
        return Enrollment::query()
            ->active()
            ->whereIn('student_id', $students->modelKeys())
            ->whereHas('course', fn (Builder $query) => $query->active())
            ->orderBy('id')
            ->get()
            ->toBase()
            ->groupBy('student_id')
            ->map(fn (Collection $enrollments): array => $enrollments
                ->map(fn (Enrollment $enrollment): int => (int) $enrollment->circle_id)
                ->unique()
                ->values()
                ->all());
    }
}
