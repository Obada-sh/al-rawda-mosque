<?php

namespace App\Actions\Courses;

use App\Exceptions\BusinessRuleViolation;
use App\Http\Requests\Courses\CourseRequest;
use App\Models\Activity;
use App\Models\Course;
use App\Models\CourseSchedule;
use App\Models\PointTransaction;
use App\Models\Recitation;
use App\Support\Dates;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Creates or updates a course together with its weekly timetable.
 */
final class SaveCourse
{
    private const COURSE_FIELDS = [
        'name', 'start_date', 'end_date', 'points_per_page', 'points_per_attendance', 'status',
    ];

    /**
     * @param  array<string, mixed>  $input
     *
     * @throws ValidationException
     * @throws BusinessRuleViolation course.records_before_start | course.records_after_end
     */
    public function handle(array $input, ?Course $course = null): Course
    {
        $data = CourseRequest::validateData($input);

        $data['start_date'] = Dates::day($data['start_date']);
        $data['end_date'] = isset($data['end_date']) ? Dates::day($data['end_date']) : null;

        return DB::transaction(function () use ($data, $course): Course {
            if ($course !== null) {
                $this->assertDatesKeepExistingRecords($course, $data['start_date'], $data['end_date']);
            }

            $course ??= new Course;
            $course->fill(Arr::only($data, self::COURSE_FIELDS))->save();

            $this->syncSchedules($course, $data['schedules']);

            return $course->refresh()->load('schedules');
        });
    }

    /**
     * Changing the course dates must not leave already-recorded sessions,
     * recitations or points outside the course.
     */
    private function assertDatesKeepExistingRecords(Course $course, string $start, ?string $end): void
    {
        $enrollmentIds = $course->enrollments()->select('id');

        $dates = array_filter([
            $course->sessions()->min('session_date'),
            $course->sessions()->max('session_date'),
            Recitation::query()->whereIn('enrollment_id', $enrollmentIds)->min('recited_on'),
            Recitation::query()->whereIn('enrollment_id', $enrollmentIds)->max('recited_on'),
            PointTransaction::query()->whereIn('enrollment_id', $enrollmentIds)->min('occurred_on'),
            PointTransaction::query()->whereIn('enrollment_id', $enrollmentIds)->max('occurred_on'),
            $this->activitiesOf($course)->min('activity_date'),
            $this->activitiesOf($course)->max('activity_date'),
        ]);

        if ($dates === []) {
            return;
        }

        $days = array_map(fn (mixed $date): string => substr((string) $date, 0, 10), $dates);

        if (min($days) < $start) {
            throw BusinessRuleViolation::because(
                'course.records_before_start',
                'توجد سجلات في الدورة قبل تاريخ البداية الجديد ('.min($days).').',
            );
        }

        if ($end !== null && max($days) > $end) {
            throw BusinessRuleViolation::because(
                'course.records_after_end',
                'توجد سجلات في الدورة بعد تاريخ النهاية الجديد ('.max($days).').',
            );
        }
    }

    /**
     * The activities held for a circle of the course, and those a student
     * of the course attended (an activity can give no points, and then it
     * has no line among the points above).
     *
     * @return Builder<Activity>
     */
    private function activitiesOf(Course $course): Builder
    {
        return Activity::query()->where(fn (Builder $query) => $query
            ->whereIn('circle_id', $course->circles()->select('id'))
            ->orWhereHas('attendances', fn (Builder $query) => $query
                ->whereIn('enrollment_id', $course->enrollments()->select('id'))));
    }

    /**
     * @param  list<array{weekday: int|string, start_time: string, end_time: string}>  $schedules
     */
    private function syncSchedules(Course $course, array $schedules): void
    {
        $weekdays = array_map(fn (array $row): int => (int) $row['weekday'], $schedules);

        $course->schedules()->whereNotIn('weekday', $weekdays)->delete();

        foreach ($schedules as $row) {
            CourseSchedule::query()->updateOrCreate(
                ['course_id' => $course->getKey(), 'weekday' => (int) $row['weekday']],
                [
                    'start_time' => $this->normalizeTime($row['start_time']),
                    'end_time' => $this->normalizeTime($row['end_time']),
                ],
            );
        }
    }

    private function normalizeTime(string $time): string
    {
        return strlen($time) === 5 ? $time.':00' : $time;
    }
}
