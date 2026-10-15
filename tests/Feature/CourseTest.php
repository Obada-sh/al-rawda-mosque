<?php

namespace Tests\Feature;

use App\Actions\Circles\DeleteCircle;
use App\Actions\Circles\SaveCircle;
use App\Actions\Courses\DeleteCourse;
use App\Actions\Courses\SaveCourse;
use App\Enums\CourseStatus;
use App\Enums\Weekday;
use App\Models\Circle;
use App\Models\Course;
use App\Models\CourseSchedule;
use App\Models\Teacher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\BuildsDomain;
use Tests\TestCase;

class CourseTest extends TestCase
{
    use BuildsDomain;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-10-07 17:00:00');
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function courseInput(array $overrides = []): array
    {
        return $overrides + [
            'name' => 'دورة الخريف',
            'start_date' => '2026-09-01',
            'end_date' => '2026-12-31',
            'points_per_page' => 2,
            'points_per_attendance' => 1,
            'schedules' => [
                ['weekday' => Weekday::Sunday->value, 'start_time' => '16:00', 'end_time' => '19:00'],
                ['weekday' => Weekday::Tuesday->value, 'start_time' => '16:00', 'end_time' => '19:00'],
                ['weekday' => Weekday::Thursday->value, 'start_time' => '16:00', 'end_time' => '19:00'],
            ],
        ];
    }

    public function test_a_course_is_created_with_its_weekly_timetable(): void
    {
        $course = app(SaveCourse::class)->handle($this->courseInput());

        $this->assertSame('دورة الخريف', $course->name);
        $this->assertSame(CourseStatus::Active, $course->status);
        $this->assertSame(2, $course->points_per_page);
        $this->assertSame(
            [Weekday::Sunday, Weekday::Tuesday, Weekday::Thursday],
            $course->schedules->sortBy(fn (CourseSchedule $schedule): int => $schedule->weekday->value)->pluck('weekday')->all(),
        );
        $this->assertSame('16:00:00', $course->schedules->first()->start_time);

        // 2026-10-06 is a Tuesday, 2026-10-07 a Wednesday.
        $this->assertTrue($course->isScheduledOn(now()->subDay()));
        $this->assertFalse($course->isScheduledOn(now()));
    }

    public function test_updating_a_course_replaces_its_timetable(): void
    {
        $course = app(SaveCourse::class)->handle($this->courseInput());

        $course = app(SaveCourse::class)->handle($this->courseInput([
            'name' => 'دورة الخريف المعدّلة',
            'schedules' => [
                ['weekday' => Weekday::Sunday->value, 'start_time' => '17:00', 'end_time' => '20:00'],
                ['weekday' => Weekday::Friday->value, 'start_time' => '09:00', 'end_time' => '11:00'],
            ],
        ]), $course);

        $this->assertSame('دورة الخريف المعدّلة', $course->name);
        $this->assertSame(2, CourseSchedule::query()->count());
        $this->assertSame('17:00:00', $course->schedules->firstWhere('weekday', Weekday::Sunday)->start_time);
        $this->assertNotNull($course->schedules->firstWhere('weekday', Weekday::Friday));
        $this->assertNull($course->schedules->firstWhere('weekday', Weekday::Tuesday));
    }

    public function test_invalid_course_input_is_refused(): void
    {
        $invalid = [
            'no name' => ['name' => ''],
            'end before start' => ['end_date' => '2026-08-01'],
            'negative points' => ['points_per_page' => -1],
            'no attendance days' => ['schedules' => []],
            'same day twice' => ['schedules' => [
                ['weekday' => 0, 'start_time' => '16:00', 'end_time' => '19:00'],
                ['weekday' => 0, 'start_time' => '10:00', 'end_time' => '12:00'],
            ]],
            'class ends before it starts' => ['schedules' => [
                ['weekday' => 0, 'start_time' => '19:00', 'end_time' => '16:00'],
            ]],
            'unknown weekday' => ['schedules' => [
                ['weekday' => 7, 'start_time' => '16:00', 'end_time' => '19:00'],
            ]],
        ];

        foreach ($invalid as $case => $overrides) {
            try {
                app(SaveCourse::class)->handle($this->courseInput($overrides));
                $this->fail("Input was accepted: {$case}");
            } catch (ValidationException) {
                $this->addToAssertionCount(1);
            }
        }

        $this->assertSame(0, Course::query()->count());
    }

    public function test_course_dates_cannot_leave_existing_records_outside(): void
    {
        $course = app(SaveCourse::class)->handle($this->courseInput());
        $enrollment = $this->enroll(course: $course, enrolledOn: '2026-09-01');
        $this->recite($enrollment, 1, 2, '2026-09-15');

        $this->assertViolates('course.records_before_start', fn () => app(SaveCourse::class)->handle(
            $this->courseInput(['start_date' => '2026-09-20']),
            $course,
        ));

        $this->assertViolates('course.records_after_end', fn () => app(SaveCourse::class)->handle(
            $this->courseInput(['end_date' => '2026-09-10']),
            $course,
        ));

        $this->assertSame('2026-09-01', $course->fresh()->start_date->toDateString());
    }

    public function test_an_unused_course_can_be_deleted_with_its_circles(): void
    {
        $course = app(SaveCourse::class)->handle($this->courseInput());
        app(SaveCircle::class)->handle($course, ['name' => 'حلقة أ']);

        app(DeleteCourse::class)->handle($course);

        $this->assertModelMissing($course);
        $this->assertSame(0, Circle::query()->count());
        $this->assertSame(0, CourseSchedule::query()->count());
    }

    public function test_a_course_with_students_cannot_be_deleted(): void
    {
        $course = Course::factory()->create();
        $this->enroll(course: $course);

        $this->assertViolates('course.has_records', fn () => app(DeleteCourse::class)->handle($course));
    }

    public function test_the_admin_creates_the_circles_of_a_course_with_just_a_name(): void
    {
        $course = Course::factory()->create();

        $circle = app(SaveCircle::class)->handle($course, ['name' => 'حلقة أبي بكر']);

        $this->assertSame($course->getKey(), $circle->course_id);
        $this->assertSame('حلقة أبي بكر', $circle->name);
        $this->assertTrue($circle->fresh()->is_active);
        $this->assertNull($circle->supervisor_teacher_id);
    }

    public function test_circle_names_are_unique_inside_a_course_only(): void
    {
        $course = Course::factory()->create();
        app(SaveCircle::class)->handle($course, ['name' => 'حلقة أ']);

        // The same name in another course is fine.
        app(SaveCircle::class)->handle(Course::factory()->create(), ['name' => 'حلقة أ']);

        $this->expectException(ValidationException::class);

        app(SaveCircle::class)->handle($course, ['name' => 'حلقة أ']);
    }

    public function test_the_supervisor_and_assistant_are_picked_from_the_teachers_list(): void
    {
        $course = Course::factory()->create();
        $supervisor = Teacher::factory()->create();
        $assistant = Teacher::factory()->create();

        $circle = app(SaveCircle::class)->handle($course, [
            'name' => 'حلقة أ',
            'supervisor_teacher_id' => $supervisor->getKey(),
            'assistant_teacher_id' => $assistant->getKey(),
        ]);

        $this->assertTrue($circle->supervisor->is($supervisor));
        $this->assertTrue($circle->assistant->is($assistant));

        // Replacing the supervisor is a plain swap.
        $replacement = Teacher::factory()->create();
        $circle = app(SaveCircle::class)->handle($course, [
            'name' => 'حلقة أ',
            'supervisor_teacher_id' => $replacement->getKey(),
            'assistant_teacher_id' => $assistant->getKey(),
        ], $circle);

        $this->assertTrue($circle->fresh()->supervisor->is($replacement));
    }

    public function test_the_supervisor_and_assistant_must_be_different_active_teachers(): void
    {
        $course = Course::factory()->create();
        $teacher = Teacher::factory()->create();

        try {
            app(SaveCircle::class)->handle($course, [
                'name' => 'حلقة أ',
                'supervisor_teacher_id' => $teacher->getKey(),
                'assistant_teacher_id' => $teacher->getKey(),
            ]);
            $this->fail('The same teacher was accepted as supervisor and assistant.');
        } catch (ValidationException) {
            $this->addToAssertionCount(1);
        }

        $inactive = Teacher::factory()->inactive()->create();

        $this->assertViolates('teacher.inactive', fn () => app(SaveCircle::class)->handle($course, [
            'name' => 'حلقة ب',
            'supervisor_teacher_id' => $inactive->getKey(),
        ]));
    }

    public function test_a_circle_with_students_cannot_be_deleted(): void
    {
        $circle = Circle::factory()->create();
        $this->enroll(circle: $circle);

        $this->assertViolates('circle.has_records', fn () => app(DeleteCircle::class)->handle($circle));

        $empty = Circle::factory()->create();
        app(DeleteCircle::class)->handle($empty);
        $this->assertModelMissing($empty);
    }
}
