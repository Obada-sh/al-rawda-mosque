<?php

namespace App\Services\Teachers;

use App\Models\Circle;
use App\Models\Course;
use App\Models\Teacher;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * The circles a teacher is responsible for, as supervisor (المشرف) or as
 * assistant (المساعد). A teacher's attendance is recorded in the courses
 * of those circles.
 */
final class TeacherCircles
{
    /**
     * The active circles of the course that the teacher supervises or
     * assists, by name: those their card of the course is for.
     *
     * @return Collection<int, Circle>
     */
    public function inCourse(Teacher $teacher, Course $course): Collection
    {
        return $this->of($teacher)
            ->where('course_id', $course->getKey())
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }

    /**
     * The teachers who supervise or assist an active circle of the course,
     * by name: those who get a card of the course.
     *
     * @return Collection<int, Teacher>
     */
    public function teachersOf(Course $course): Collection
    {
        $circles = Circle::query()
            ->where('course_id', $course->getKey())
            ->where('is_active', true);

        return Teacher::query()
            ->where(fn (Builder $query) => $query
                ->whereIn('id', (clone $circles)->whereNotNull('supervisor_teacher_id')->select('supervisor_teacher_id'))
                ->orWhereIn('id', (clone $circles)->whereNotNull('assistant_teacher_id')->select('assistant_teacher_id')))
            ->orderBy('full_name')
            ->get();
    }

    /**
     * Whether the teacher supervises or assists a circle of the course
     * (an active circle or not: past attendance stays valid).
     */
    public function teachesIn(Teacher $teacher, Course $course): bool
    {
        return $this->of($teacher)->where('course_id', $course->getKey())->exists();
    }

    /**
     * What the teacher does in each circle: "مشرف حلقة النور، مساعد حلقة الفجر".
     *
     * @param  iterable<Circle>  $circles
     */
    public static function roles(Teacher $teacher, iterable $circles): string
    {
        $roles = [];

        foreach ($circles as $circle) {
            $role = (int) $circle->supervisor_teacher_id === (int) $teacher->getKey() ? 'مشرف' : 'مساعد';
            $roles[] = "{$role} {$circle->name}";
        }

        return implode('، ', $roles);
    }

    /** @return Builder<Circle> */
    private function of(Teacher $teacher): Builder
    {
        return Circle::query()->where(fn (Builder $query) => $query
            ->where('supervisor_teacher_id', $teacher->getKey())
            ->orWhere('assistant_teacher_id', $teacher->getKey()));
    }
}
