<?php

namespace App\Actions\Activities;

use App\Enums\ActivityAudience;
use App\Exceptions\BusinessRuleViolation;
use App\Http\Requests\Activities\ActivityRequest;
use App\Models\Activity;
use App\Models\ActivityAttendance;
use App\Models\Circle;
use App\Models\Enrollment;
use App\Services\Enrollments\EnrollmentCalendar;
use App\Services\Points\PointsLedger;
use App\Support\Dates;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Records an activity, or corrects one when $activity is given: what it
 * was, for whom, and who attended. The points follow in the same step:
 * each student on the list has the points of the activity, and nobody
 * else has.
 *
 * A student attends as the student of a course (an enrollment), like
 * everything else they do, so the points go to that course.
 */
final class SaveActivity
{
    public function __construct(
        private readonly EnrollmentCalendar $calendar,
        private readonly PointsLedger $points,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     *
     * @throws ValidationException
     * @throws BusinessRuleViolation circle.inactive | activity.outside_course | activity.student_twice
     *                               | activity.other_course | student.archived | date.*
     */
    public function handle(array $input, ?Activity $activity = null): Activity
    {
        $data = ActivityRequest::validateData($input);

        $date = Dates::parse($data['activity_date']);
        $circle = ActivityAudience::from($data['audience']) === ActivityAudience::Circle
            ? Circle::query()->with('course')->findOrFail($data['circle_id'])
            : null;

        if ($circle !== null) {
            $this->assertCircleCanHold($circle, $date, $activity);
        }

        $attendees = Enrollment::query()
            ->with(['student', 'course'])
            ->whereKey($data['enrollment_ids'] ?? [])
            ->get();

        $this->assertCanAttend($attendees, $circle, $date, $activity);

        return DB::transaction(function () use ($activity, $data, $date, $circle, $attendees): Activity {
            $activity ??= new Activity;

            $activity->fill([
                'title' => $data['title'],
                'activity_date' => $date->toDateString(),
                'circle_id' => $circle?->getKey(),
                'points' => (int) $data['points'],
                'notes' => $data['notes'] ?? null,
            ])->save();

            $this->writeAttendees($activity, $attendees->modelKeys());

            return $activity;
        });
    }

    /**
     * @throws BusinessRuleViolation circle.inactive | activity.outside_course
     */
    private function assertCircleCanHold(Circle $circle, CarbonInterface $date, ?Activity $activity): void
    {
        // A circle that was closed since keeps the activities it already has.
        $isSameCircle = $activity !== null && (int) $activity->circle_id === (int) $circle->getKey();

        if (! $circle->is_active && ! $isSameCircle) {
            throw BusinessRuleViolation::because('circle.inactive', 'الحلقة المختارة غير نشطة.');
        }

        if (! $circle->course->covers($date)) {
            throw BusinessRuleViolation::because('activity.outside_course', 'تاريخ النشاط خارج مدة دورة هذه الحلقة.');
        }
    }

    /**
     * @param  Collection<int, Enrollment>  $attendees
     *
     * @throws BusinessRuleViolation activity.student_twice | activity.other_course | student.archived | date.*
     */
    private function assertCanAttend(Collection $attendees, ?Circle $circle, CarbonInterface $date, ?Activity $activity): void
    {
        $alreadyAttending = array_map('intval', $activity?->attendances()->pluck('enrollment_id')->all() ?? []);
        $seen = [];

        foreach ($attendees as $enrollment) {
            $name = $enrollment->student->full_name;

            // A student in two courses is one person: they attend once, and the points go to one course.
            if (isset($seen[$enrollment->student_id])) {
                throw BusinessRuleViolation::because(
                    'activity.student_twice',
                    "الطالب {$name} مختار مرتين، مرة عن كل دورة هو فيها. اختره مرة واحدة.",
                );
            }

            $seen[$enrollment->student_id] = true;

            if ($circle !== null && (int) $enrollment->course_id !== (int) $circle->course_id) {
                throw BusinessRuleViolation::because('activity.other_course', "الطالب {$name} ليس في دورة هذه الحلقة.");
            }

            // Whoever is on the list already stays on it; only a new name is refused.
            if ($enrollment->student->isArchived() && ! in_array((int) $enrollment->getKey(), $alreadyAttending, true)) {
                throw BusinessRuleViolation::because('student.archived', "الطالب {$name} مؤرشف. أعد تفعيله قبل تسجيل حضوره.");
            }

            try {
                $this->calendar->assertDateAllowed($enrollment, $date);
            } catch (BusinessRuleViolation $violation) {
                // The rule speaks of "the student": with a whole list, it must say which one.
                throw BusinessRuleViolation::because($violation->rule, "{$name}: {$violation->getMessage()}");
            }
        }
    }

    /**
     * Makes the list of those who attended exactly the given one, and
     * their points what the activity gives now.
     *
     * @param  array<int, int|string>  $enrollmentIds
     */
    private function writeAttendees(Activity $activity, array $enrollmentIds): void
    {
        // The points of a student taken off the list go with the line (the database cascades).
        $activity->attendances()->whereNotIn('enrollment_id', $enrollmentIds)->delete();

        $present = $activity->attendances()->pluck('enrollment_id')->all();

        foreach (array_diff($enrollmentIds, $present) as $enrollmentId) {
            ActivityAttendance::query()->create([
                'activity_id' => $activity->getKey(),
                'enrollment_id' => $enrollmentId,
            ]);
        }

        foreach ($activity->attendances()->get() as $attendance) {
            $attendance->setRelation('activity', $activity);

            $this->points->syncForActivityAttendance($attendance);
        }
    }
}
