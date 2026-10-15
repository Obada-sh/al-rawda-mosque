<?php

namespace Tests\Feature;

use App\Actions\Activities\DeleteActivity;
use App\Actions\Activities\SaveActivity;
use App\Actions\Circles\DeleteCircle;
use App\Actions\Courses\DeleteCourse;
use App\Actions\Courses\SaveCourse;
use App\Actions\Enrollments\DeleteEnrollment;
use App\Actions\Enrollments\UpdateEnrollmentDates;
use App\Actions\Points\DeleteManualPoints;
use App\Actions\Students\ArchiveStudent;
use App\Enums\ActivityAudience;
use App\Enums\PointSource;
use App\Exceptions\BusinessRuleViolation;
use App\Models\Activity;
use App\Models\ActivityAttendance;
use App\Models\Circle;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\PointTransaction;
use App\Models\Student;
use App\Services\Statistics\ReportFilter;
use App\Services\Statistics\StudentStandings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\BuildsDomain;
use Tests\TestCase;

class ActivityTest extends TestCase
{
    use BuildsDomain;
    use RefreshDatabase;

    private Course $course;

    private Circle $circle;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-10-07 17:00:00');

        $this->course = Course::factory()->create(['start_date' => '2026-09-01']);
        $this->circle = Circle::factory()->create(['course_id' => $this->course->getKey()]);
    }

    private function member(?string $name = null): Enrollment
    {
        return $this->enroll(
            $name === null ? null : Student::factory()->create(['full_name' => $name]),
            $this->course,
            $this->circle,
        );
    }

    /**
     * @param  array<string, mixed>  $changes
     * @return array<string, mixed> what the form sends when the activity is edited
     */
    private function formOf(Activity $activity, array $changes = []): array
    {
        return $changes + [
            'title' => $activity->title,
            'activity_date' => $activity->activity_date->toDateString(),
            'audience' => $activity->audience()->value,
            'circle_id' => $activity->circle_id,
            'points' => $activity->points,
            'notes' => $activity->notes,
            'enrollment_ids' => $activity->attendances()->pluck('enrollment_id')->all(),
        ];
    }

    public function test_each_student_who_attended_a_circle_activity_gains_its_points(): void
    {
        $came = $this->member();
        $alsoCame = $this->member();
        $stayedHome = $this->member();

        $activity = $this->holdActivity([$came, $alsoCame], $this->circle, [
            'title' => 'مسابقة الحفظ',
            'activity_date' => '2026-10-05',
            'points' => 7,
        ]);

        $this->assertSame(ActivityAudience::Circle, $activity->audience());
        $this->assertSame($this->circle->getKey(), $activity->circle_id);
        $this->assertSame('2026-10-05', $activity->activity_date->toDateString());
        $this->assertEqualsCanonicalizing(
            [$came->getKey(), $alsoCame->getKey()],
            $activity->attendances()->pluck('enrollment_id')->all(),
        );

        $points = $came->pointTransactions()->sole();
        $this->assertSame(
            [7, PointSource::Activity, 'مسابقة الحفظ', '2026-10-05'],
            [$points->points, $points->source, $points->reason, $points->occurred_on->toDateString()],
        );
        $this->assertSame(7, (int) $alsoCame->pointTransactions()->sum('points'));
        $this->assertSame(0, $stayedHome->pointTransactions()->count());
    }

    public function test_an_activity_of_the_whole_mosque_takes_students_of_any_course(): void
    {
        $here = $this->member();
        $otherCourse = Course::factory()->create(['start_date' => '2026-09-01']);
        $there = $this->enroll(course: $otherCourse);

        $activity = $this->holdActivity([$here, $there]);

        $this->assertSame(ActivityAudience::Mosque, $activity->audience());
        $this->assertNull($activity->circle_id);
        $this->assertSame(2, $activity->attendances()->count());

        // The points go to the course each student is in.
        $this->assertSame(5, (int) $here->pointTransactions()->sum('points'));
        $this->assertSame(5, (int) $there->pointTransactions()->sum('points'));
    }

    public function test_a_circle_named_for_a_mosque_activity_is_not_kept(): void
    {
        $activity = $this->holdActivity(overrides: ['circle_id' => $this->circle->getKey()]);

        $this->assertNull($activity->circle_id);
    }

    public function test_correcting_the_activity_corrects_the_points_of_everybody(): void
    {
        $first = $this->member();
        $second = $this->member();
        $activity = $this->holdActivity([$first, $second], $this->circle);

        app(SaveActivity::class)->handle($this->formOf($activity, [
            'title' => 'رحلة الخريف',
            'activity_date' => '2026-10-01',
            'points' => 12,
        ]), $activity);

        foreach ([$first, $second] as $enrollment) {
            $points = $enrollment->pointTransactions()->sole();

            $this->assertSame(
                [12, 'رحلة الخريف', '2026-10-01'],
                [$points->points, $points->reason, $points->occurred_on->toDateString()],
            );
        }

        $this->assertSame(1, Activity::query()->count());
    }

    public function test_an_activity_without_points_keeps_its_list_and_gives_nothing(): void
    {
        $enrollment = $this->member();
        $activity = $this->holdActivity([$enrollment], $this->circle);

        app(SaveActivity::class)->handle($this->formOf($activity, ['points' => 0]), $activity);

        $this->assertSame(1, $activity->attendances()->count());
        $this->assertSame(0, PointTransaction::query()->count());

        // And the points come back with the number.
        app(SaveActivity::class)->handle($this->formOf($activity->refresh(), ['points' => 3]), $activity);

        $this->assertSame(3, (int) $enrollment->pointTransactions()->sum('points'));
    }

    public function test_taking_a_student_off_the_list_takes_their_points_and_adding_one_gives_them(): void
    {
        $stays = $this->member();
        $leaves = $this->member();
        $joins = $this->member();
        $activity = $this->holdActivity([$stays, $leaves], $this->circle);

        app(SaveActivity::class)->handle($this->formOf($activity, [
            'enrollment_ids' => [$stays->getKey(), $joins->getKey()],
        ]), $activity);

        $this->assertEqualsCanonicalizing(
            [$stays->getKey(), $joins->getKey()],
            $activity->attendances()->pluck('enrollment_id')->all(),
        );
        $this->assertSame(5, (int) $stays->pointTransactions()->sum('points'));
        $this->assertSame(5, (int) $joins->pointTransactions()->sum('points'));
        $this->assertSame(0, $leaves->pointTransactions()->count());
        // The student who stayed has one line of points, not two.
        $this->assertSame(1, $stays->pointTransactions()->count());
    }

    public function test_deleting_an_activity_removes_its_list_and_its_points(): void
    {
        $enrollment = $this->member();
        $activity = $this->holdActivity([$enrollment], $this->circle);
        $manual = $this->award($enrollment, 4);

        app(DeleteActivity::class)->handle($activity);

        $this->assertSame(0, Activity::query()->count());
        $this->assertSame(0, ActivityAttendance::query()->count());
        $this->assertSame([$manual->getKey()], $enrollment->pointTransactions()->pluck('id')->all());
    }

    public function test_the_points_of_an_activity_are_not_changed_by_hand(): void
    {
        $enrollment = $this->member();
        $this->holdActivity([$enrollment], $this->circle);

        $this->assertViolates('points.not_manual', fn () => app(DeleteManualPoints::class)->handle($enrollment->pointTransactions()->sole()));
    }

    public function test_a_student_of_two_courses_attends_once(): void
    {
        $student = Student::factory()->create(['full_name' => 'سامر الحلبي']);
        $inThisCourse = $this->enroll($student, $this->course, $this->circle);
        $inAnotherCourse = $this->enroll($student, Course::factory()->create(['start_date' => '2026-09-01']));

        try {
            $this->holdActivity([$inThisCourse, $inAnotherCourse]);
            $this->fail('The same student was accepted twice.');
        } catch (BusinessRuleViolation $violation) {
            $this->assertSame('activity.student_twice', $violation->rule);
            $this->assertStringContainsString('سامر الحلبي', $violation->getMessage());
        }

        $this->assertSame(0, Activity::query()->count());
    }

    public function test_a_circle_activity_is_for_students_of_its_course(): void
    {
        $outsider = $this->enroll(course: Course::factory()->create(['start_date' => '2026-09-01']));

        $this->assertViolates('activity.other_course', fn () => $this->holdActivity([$outsider], $this->circle));
        $this->assertSame(0, Activity::query()->count());
    }

    public function test_nobody_attends_before_joining_the_course_or_after_leaving_it(): void
    {
        $late = $this->enroll(Student::factory()->create(['full_name' => 'عمر المتأخر']), $this->course, $this->circle, '2026-10-03');

        try {
            $this->holdActivity([$late], $this->circle, ['activity_date' => '2026-10-01']);
            $this->fail('A student attended before joining the course.');
        } catch (BusinessRuleViolation $violation) {
            $this->assertSame('date.before_enrollment', $violation->rule);
            // With a whole list on the screen, the message names the student.
            $this->assertStringContainsString('عمر المتأخر', $violation->getMessage());
        }

        $gone = $this->member();
        app(UpdateEnrollmentDates::class)->handle($gone, ['enrolled_on' => '2026-09-01', 'left_on' => '2026-09-20']);

        $this->assertViolates('date.after_withdrawal', fn () => $this->holdActivity([$gone->refresh()], $this->circle, ['activity_date' => '2026-10-01']));
        $this->assertViolates('date.before_course_start', fn () => $this->holdActivity([$this->member()], overrides: ['activity_date' => '2026-08-20']));
    }

    public function test_a_circle_activity_falls_inside_the_course_of_the_circle(): void
    {
        $this->assertViolates('activity.outside_course', fn () => $this->holdActivity([], $this->circle, ['activity_date' => '2026-08-20']));
    }

    public function test_a_closed_circle_takes_no_new_activity_but_keeps_the_ones_it_has(): void
    {
        $activity = $this->holdActivity([], $this->circle);
        $this->circle->update(['is_active' => false]);

        $this->assertViolates('circle.inactive', fn () => $this->holdActivity([], $this->circle->refresh()));

        $corrected = app(SaveActivity::class)->handle($this->formOf($activity, ['title' => 'لقاء الأهالي']), $activity);
        $this->assertSame('لقاء الأهالي', $corrected->title);
    }

    public function test_an_archived_student_is_not_added_but_stays_on_a_list_they_are_on(): void
    {
        $enrollment = $this->member();
        $activity = $this->holdActivity([$enrollment], $this->circle);

        app(ArchiveStudent::class)->handle($enrollment->student);

        $this->assertViolates('student.archived', fn () => $this->holdActivity([$enrollment->refresh()], $this->circle));

        app(SaveActivity::class)->handle($this->formOf($activity, ['points' => 9]), $activity);
        $this->assertSame(9, (int) $enrollment->pointTransactions()->sum('points'));
    }

    public function test_what_is_typed_is_checked_before_anything_is_stored(): void
    {
        $enrollment = $this->member();

        $invalid = [
            'no title' => ['title' => ''],
            'a date in the future' => ['activity_date' => '2026-10-08'],
            'an unknown audience' => ['audience' => 'everybody'],
            'negative points' => ['points' => -1],
            'too many points' => ['points' => 1001],
            'a student who does not exist' => ['enrollment_ids' => [$enrollment->getKey(), 999999]],
            'the same student twice' => ['enrollment_ids' => [$enrollment->getKey(), $enrollment->getKey()]],
        ];

        foreach ($invalid as $case => $overrides) {
            try {
                $this->holdActivity([$enrollment], $this->circle, $overrides);
                $this->fail("Accepted: {$case}.");
            } catch (ValidationException) {
                $this->assertSame(0, Activity::query()->count(), $case);
            }
        }

        // A circle activity names its circle.
        $this->expectException(ValidationException::class);
        $this->holdActivity(overrides: ['audience' => ActivityAudience::Circle->value]);
    }

    public function test_points_typed_with_arabic_digits_are_understood(): void
    {
        $activity = $this->holdActivity([$this->member()], $this->circle, ['points' => '١٥']);

        $this->assertSame(15, $activity->points);
    }

    public function test_activity_points_count_in_the_reports(): void
    {
        $enrollment = $this->member();
        $this->holdActivity([$enrollment], $this->circle, ['points' => 8, 'activity_date' => '2026-10-02']);

        $inPeriod = app(StudentStandings::class)->for(new ReportFilter(from: '2026-10-01', until: '2026-10-07'))->sole();
        $before = app(StudentStandings::class)->for(new ReportFilter(from: '2026-09-01', until: '2026-09-30'))->sole();

        $this->assertSame(8, $inPeriod->points);
        $this->assertSame(0, $before->points);
    }

    public function test_what_attended_an_activity_is_not_deleted_from_under_it(): void
    {
        $enrollment = $this->member();
        // No points: the list alone must hold everything in place.
        $this->holdActivity([$enrollment], $this->circle, ['points' => 0, 'activity_date' => '2026-10-02']);

        $this->assertViolates('enrollment.has_records', fn () => app(DeleteEnrollment::class)->handle($enrollment));
        $this->assertViolates('circle.has_records', fn () => app(DeleteCircle::class)->handle($this->circle));

        // The enrollment cannot begin after the activity, and the course cannot either.
        $this->assertViolates('enrollment.records_before_enrolled_on', fn () => app(UpdateEnrollmentDates::class)->handle($enrollment, ['enrolled_on' => '2026-10-04']));
    }

    public function test_a_course_is_not_deleted_or_shortened_over_the_activities_of_its_circles(): void
    {
        $this->holdActivity([], $this->circle, ['activity_date' => '2026-10-02']);

        $this->assertViolates('course.has_records', fn () => app(DeleteCourse::class)->handle($this->course));

        $form = [
            'name' => $this->course->name,
            'start_date' => '2026-10-03',
            'end_date' => null,
            'points_per_page' => 2,
            'points_per_attendance' => 1,
            'schedules' => $this->course->schedules
                ->map(fn ($schedule): array => ['weekday' => $schedule->weekday->value, 'start_time' => '16:00', 'end_time' => '19:00'])
                ->all(),
        ];

        $this->assertViolates('course.records_before_start', fn () => app(SaveCourse::class)->handle($form, $this->course));
    }
}
