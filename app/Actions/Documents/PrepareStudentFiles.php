<?php

namespace App\Actions\Documents;

use App\Http\Requests\Documents\StudentFilesRequest;
use App\Models\Circle;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Student;
use App\Services\Statistics\StudentStandings;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Builds the files of many students at once, each like the file of one
 * student (StudentFileBuilder), by student name:
 *
 * - every student in the system (StudentFiles::ALL), archived ones only
 *   when asked;
 * - the students of a course or of a circle who did not leave it
 *   (COURSE, CIRCLE): their whole file, or only that course (history);
 * - the students ticked in the list (CHOSEN).
 */
final class PrepareStudentFiles
{
    public function __construct(
        private readonly StudentStandings $standings,
        private readonly StudentFileBuilder $builder,
    ) {}

    /**
     * @param  array<string, mixed>  $input  see StudentFilesRequest
     *
     * @throws ValidationException
     */
    public function handle(array $input): StudentFiles
    {
        $data = StudentFilesRequest::validateData($input);

        [$scope, $course, $students] = match ($data['scope']) {
            StudentFiles::ALL => $this->everyStudent((bool) ($data['archived'] ?? false)),
            StudentFiles::COURSE => $this->ofCourse(Course::query()->findOrFail($data['course_id'])),
            StudentFiles::CIRCLE => $this->ofCircle(Circle::query()->with('course')->findOrFail($data['circle_id'])),
            StudentFiles::CHOSEN => ['الطلاب المحدَّدون', null, $this->sorted(Student::query()->whereKey($data['student_ids']))],
        };

        // With a course or a circle, each file may hold that course only.
        $onlyCourse = $course !== null && ($data['history'] ?? SheetOptions::ALL_COURSES) === SheetOptions::THIS_COURSE ? $course : null;
        $standings = $this->standings->forStudents($students->modelKeys());

        return new StudentFiles(
            scope: $scope,
            onlyCourse: $onlyCourse?->name,
            files: $students
                ->map(fn (Student $student): StudentFile => $this->builder->build($student, $onlyCourse?->getKey(), $standings))
                ->values()
                ->all(),
        );
    }

    /**
     * @return array{string, null, Collection<int, Student>}
     */
    private function everyStudent(bool $withArchived): array
    {
        return [
            $withArchived ? 'كل الطلاب، والمؤرشفون معهم' : 'كل الطلاب',
            null,
            $this->sorted(Student::query()->when(! $withArchived, fn (Builder $query) => $query->active())),
        ];
    }

    /**
     * @return array{string, Course, Collection<int, Student>}
     */
    private function ofCourse(Course $course): array
    {
        return [
            "طلاب {$course->name}",
            $course,
            $this->sorted(Student::query()->whereIn('id', Enrollment::query()->active()->where('course_id', $course->getKey())->select('student_id'))),
        ];
    }

    /**
     * @return array{string, Course, Collection<int, Student>}
     */
    private function ofCircle(Circle $circle): array
    {
        return [
            "طلاب {$circle->name} — {$circle->course->name}",
            $circle->course,
            $this->sorted(Student::query()->whereIn('id', Enrollment::query()->active()->where('circle_id', $circle->getKey())->select('student_id'))),
        ];
    }

    /**
     * @param  Builder<Student>  $query
     * @return Collection<int, Student>
     */
    private function sorted(Builder $query): Collection
    {
        return $query->orderBy('full_name')->orderBy('id')->get();
    }
}
