<?php

namespace App\Filament\Support;

use App\Enums\ActivityAudience;
use App\Enums\Weekday;
use App\Filament\Support\Fields\EnrollmentSelect;
use App\Models\Activity;
use App\Models\Circle;
use App\Models\Course;
use App\Models\Department;
use App\Models\Enrollment;
use App\Models\Market;
use App\Models\TajweedLevel;
use App\Models\Teacher;
use Illuminate\Database\Eloquent\Builder;

/**
 * The choices offered by the drop-down lists of the panel.
 *
 * A list offers what can be chosen today (active teachers, active
 * departments...). When a record is edited, the value it already holds is
 * kept in the list even if it has since been deactivated, so the form does
 * not lose it. The rules themselves are enforced by the Actions.
 */
final class Options
{
    private const WEEK_ORDER = [
        Weekday::Saturday, Weekday::Sunday, Weekday::Monday, Weekday::Tuesday,
        Weekday::Wednesday, Weekday::Thursday, Weekday::Friday,
    ];

    /**
     * The days of the week, starting on Saturday.
     *
     * @return array<int, string> weekday number => name
     */
    public static function weekdays(): array
    {
        $options = [];

        foreach (self::WEEK_ORDER as $weekday) {
            $options[$weekday->value] = $weekday->label();
        }

        return $options;
    }

    /**
     * Position of a weekday in a week that starts on Saturday.
     */
    public static function weekPosition(Weekday $weekday): int
    {
        return (int) array_search($weekday, self::WEEK_ORDER, true);
    }

    /**
     * @return array<int, string> id => name
     */
    public static function teachers(mixed $current = null): array
    {
        return Teacher::query()
            ->where('is_active', true)
            ->when(filled($current), fn ($query) => $query->orWhere('id', $current))
            ->orderBy('full_name')
            ->pluck('full_name', 'id')
            ->all();
    }

    /**
     * @return array<int, string>
     */
    public static function departments(mixed $current = null): array
    {
        return Department::query()
            ->where('is_active', true)
            ->when(filled($current), fn ($query) => $query->orWhere('id', $current))
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
        return Department::query()
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    /**
     * @return array<int, string>
     */
    public static function tajweedLevels(): array
    {
        return TajweedLevel::query()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    /**
     * Every course, newest first (for filters and reports).
     *
     * @return array<int, string>
     */
    public static function courses(): array
    {
        return Course::query()
            ->orderByDesc('start_date')
            ->orderByDesc('id')
            ->pluck('name', 'id')
            ->all();
    }

    /**
     * The active course when there is exactly one, so that screens can
     * start on it instead of asking for a course that is the only choice.
     */
    public static function soleActiveCourseId(): ?int
    {
        $ids = Course::query()->active()->limit(2)->pluck('id');

        return $ids->count() === 1 ? (int) $ids->first() : null;
    }

    /**
     * The courses a student can be enrolled in.
     *
     * @return array<int, string>
     */
    public static function activeCourses(): array
    {
        return Course::query()
            ->active()
            ->orderByDesc('start_date')
            ->orderByDesc('id')
            ->pluck('name', 'id')
            ->all();
    }

    /**
     * The courses a student is or was enrolled in, newest first.
     *
     * @return array<int, string>
     */
    public static function coursesOfStudent(mixed $studentId): array
    {
        if (blank($studentId)) {
            return [];
        }

        return Course::query()
            ->whereHas('enrollments', fn (Builder $query) => $query->where('student_id', $studentId))
            ->orderByDesc('start_date')
            ->orderByDesc('id')
            ->pluck('name', 'id')
            ->all();
    }

    /**
     * The courses in which a teacher supervises or assists a circle,
     * newest first: those in which their attendance can be recorded.
     *
     * @return array<int, string>
     */
    public static function coursesOfTeacher(mixed $teacherId): array
    {
        if (blank($teacherId)) {
            return [];
        }

        return Course::query()
            ->whereHas('circles', fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->where('supervisor_teacher_id', $teacherId)
                ->orWhere('assistant_teacher_id', $teacherId)))
            ->orderByDesc('start_date')
            ->orderByDesc('id')
            ->pluck('name', 'id')
            ->all();
    }

    /**
     * The running courses in which a teacher supervises or assists an
     * active circle, newest first: those they have an attendance card of.
     *
     * @return array<int, string>
     */
    public static function activeCoursesOfTeacher(mixed $teacherId): array
    {
        if (blank($teacherId)) {
            return [];
        }

        return Course::query()
            ->active()
            ->whereHas('circles', fn (Builder $query) => $query
                ->where('is_active', true)
                ->where(fn (Builder $query) => $query
                    ->where('supervisor_teacher_id', $teacherId)
                    ->orWhere('assistant_teacher_id', $teacherId)))
            ->orderByDesc('start_date')
            ->orderByDesc('id')
            ->pluck('name', 'id')
            ->all();
    }

    /**
     * The points markets that sell, newest first, each with its course
     * ("سوق رمضان — دورة الخريف").
     *
     * @return array<int, string>
     */
    public static function openMarkets(): array
    {
        return self::marketChoices(Market::query()->open());
    }

    /**
     * Every points market, newest first (for filters).
     *
     * @return array<int, string>
     */
    public static function markets(): array
    {
        return self::marketChoices(Market::query());
    }

    /**
     * @param  Builder<Market>  $query
     * @return array<int, string>
     */
    private static function marketChoices(Builder $query): array
    {
        return $query
            ->with('course')
            ->orderByDesc('id')
            ->get()
            ->mapWithKeys(fn (Market $market): array => [$market->getKey() => "{$market->name} — {$market->course->name}"])
            ->all();
    }

    /**
     * Every circle of one course, active or not (for filters and reports).
     *
     * @return array<int, string>
     */
    public static function circlesOfCourse(mixed $courseId): array
    {
        if (blank($courseId)) {
            return [];
        }

        return Circle::query()
            ->where('course_id', $courseId)
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    /**
     * The students of a course who can be marked present: those still in
     * it and not archived, by name; with their circle when the whole
     * course is listed.
     *
     * @return array<int, string> enrollment id => name
     */
    public static function studentsOfCourse(mixed $courseId, mixed $circleId = null): array
    {
        if (blank($courseId)) {
            return [];
        }

        return Enrollment::query()
            ->with(['student', 'circle'])
            ->where('course_id', $courseId)
            ->when(filled($circleId), fn (Builder $query) => $query->where('circle_id', $circleId))
            ->whereNull('left_on')
            ->whereHas('student', fn (Builder $query) => $query->active())
            ->get()
            ->sortBy(fn (Enrollment $enrollment): string => $enrollment->student->full_name)
            ->mapWithKeys(fn (Enrollment $enrollment): array => [
                $enrollment->getKey() => filled($circleId)
                    ? $enrollment->student->full_name
                    : "{$enrollment->student->full_name} — {$enrollment->circle->name}",
            ])
            ->all();
    }

    /**
     * The students who can be marked as having attended an activity: those
     * of the circle for a circle's activity, every student of a running
     * course for an activity of the whole mosque. Those already on the list
     * of the activity stay on offer, whatever became of them since.
     *
     * A choice is "a student in a course" (an enrollment), so a student of
     * two running courses is offered once for each; the action lets only
     * one of the two be chosen.
     *
     * @return array<int, string> enrollment id => what the admin reads
     */
    public static function activityAttendees(ActivityAudience $audience, mixed $circleId, ?Activity $activity = null): array
    {
        if ($audience === ActivityAudience::Circle && blank($circleId)) {
            return [];
        }

        $alreadyAttending = $activity === null ? [] : $activity->attendances()->pluck('enrollment_id')->all();

        $current = fn (Builder $query): Builder => $query
            ->whereNull('left_on')
            ->whereHas('student', fn (Builder $query) => $query->active());

        $enrollments = Enrollment::query()
            ->with(['student', 'circle', 'course'])
            ->where(fn (Builder $query) => $query
                ->where(fn (Builder $query) => $audience === ActivityAudience::Circle
                    ? $current($query)->where('circle_id', $circleId)
                    : $current($query)->whereHas('course', fn (Builder $query) => $query->active()))
                ->orWhereIn('id', $alreadyAttending))
            ->get();

        // Circle by circle, the way the admin reads the list of who came.
        $sorted = $audience === ActivityAudience::Circle
            ? $enrollments->sortBy(fn (Enrollment $enrollment): string => $enrollment->student->full_name)
            : $enrollments->sortBy(fn (Enrollment $enrollment): array => [
                $enrollment->course->name,
                $enrollment->circle->name,
                $enrollment->student->full_name,
            ]);

        return $sorted
            ->mapWithKeys(fn (Enrollment $enrollment): array => [
                $enrollment->getKey() => $audience === ActivityAudience::Circle
                    ? $enrollment->student->full_name
                    : EnrollmentSelect::label($enrollment),
            ])
            ->all();
    }

    /**
     * The circles of one course a student can be placed in.
     *
     * @return array<int, string>
     */
    public static function circles(mixed $courseId, mixed $current = null): array
    {
        if (blank($courseId)) {
            return [];
        }

        return Circle::query()
            ->where('course_id', $courseId)
            ->where(fn ($query) => $query
                ->where('is_active', true)
                ->when(filled($current), fn ($query) => $query->orWhere('id', $current)))
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }
}
