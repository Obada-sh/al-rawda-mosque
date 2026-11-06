<?php

namespace App\Filament\English\Support;

use App\Models\English\EnglishCourse;
use App\Models\English\EnglishDepartment;
use App\Models\English\EnglishEnrollment;
use App\Models\English\EnglishHall;
use App\Models\English\EnglishLevel;
use App\Models\English\EnglishTeacher;
use Illuminate\Database\Eloquent\Builder;

/**
 * The choices offered by the drop-down lists of the English section.
 *
 * A list offers what can be chosen today (active teachers, active halls...);
 * the value a record already holds is kept in the list even if it has
 * since been deactivated. The rules themselves are enforced by the Actions.
 */
final class EnglishOptions
{
    /**
     * @return array<int, string> id => name, in the levels' order
     */
    public static function levels(): array
    {
        return EnglishLevel::query()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    /**
     * @return array<int, string>
     */
    public static function teachers(mixed $current = null): array
    {
        return EnglishTeacher::query()
            ->where('is_active', true)
            ->when(filled($current), fn (Builder $query) => $query->orWhere('id', $current))
            ->orderBy('full_name')
            ->pluck('full_name', 'id')
            ->all();
    }

    /**
     * Every course, newest first (for filters and reports).
     *
     * @return array<int, string>
     */
    public static function courses(): array
    {
        return EnglishCourse::query()
            ->orderByDesc('start_date')
            ->orderByDesc('id')
            ->pluck('name', 'id')
            ->all();
    }

    /**
     * The running courses, newest first.
     *
     * @return array<int, string>
     */
    public static function activeCourses(): array
    {
        return EnglishCourse::query()
            ->active()
            ->orderByDesc('start_date')
            ->orderByDesc('id')
            ->pluck('name', 'id')
            ->all();
    }

    /**
     * The running course when there is exactly one, so that screens can
     * start on it.
     */
    public static function soleActiveCourseId(): ?int
    {
        $ids = EnglishCourse::query()->active()->limit(2)->pluck('id');

        return $ids->count() === 1 ? (int) $ids->first() : null;
    }

    /**
     * The halls of a course a student can be placed in.
     *
     * @return array<int, string>
     */
    public static function halls(mixed $courseId, mixed $current = null): array
    {
        if (blank($courseId)) {
            return [];
        }

        return EnglishHall::query()
            ->where('english_course_id', $courseId)
            ->where(fn (Builder $query) => $query
                ->where('is_active', true)
                ->when(filled($current), fn (Builder $query) => $query->orWhere('id', $current)))
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    /**
     * Every hall of a course, active or not (for filters).
     *
     * @return array<int, string>
     */
    public static function hallsOfCourse(mixed $courseId): array
    {
        if (blank($courseId)) {
            return [];
        }

        return EnglishHall::query()
            ->where('english_course_id', $courseId)
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    /**
     * The students still in a course, by name, with their hall when the
     * whole course is listed.
     *
     * @return array<int, string> enrollment id => name
     */
    public static function studentsOfCourse(mixed $courseId, mixed $hallId = null): array
    {
        if (blank($courseId)) {
            return [];
        }

        return EnglishEnrollment::query()
            ->with(['student', 'hall'])
            ->where('english_course_id', $courseId)
            ->when(filled($hallId), fn (Builder $query) => $query->where('english_hall_id', $hallId))
            ->whereNull('left_on')
            ->get()
            ->sortBy(fn (EnglishEnrollment $enrollment): string => $enrollment->student->full_name)
            ->mapWithKeys(fn (EnglishEnrollment $enrollment): array => [
                $enrollment->getKey() => filled($hallId)
                    ? $enrollment->student->full_name
                    : "{$enrollment->student->full_name} — {$enrollment->hall->name}",
            ])
            ->all();
    }

    /**
     * @return array<int, string>
     */
    public static function departments(mixed $current = null): array
    {
        return EnglishDepartment::query()
            ->where('is_active', true)
            ->when(filled($current), fn (Builder $query) => $query->orWhere('id', $current))
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    /**
     * Every department, active or not (for filters and reports).
     *
     * @return array<int, string>
     */
    public static function allDepartments(): array
    {
        return EnglishDepartment::query()
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }
}
