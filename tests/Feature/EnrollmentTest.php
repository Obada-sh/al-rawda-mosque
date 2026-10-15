<?php

namespace Tests\Feature;

use App\Actions\Enrollments\DeleteEnrollment;
use App\Actions\Enrollments\EnrollStudent;
use App\Actions\Enrollments\UpdateEnrollmentDates;
use App\Models\Circle;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\BuildsDomain;
use Tests\TestCase;

class EnrollmentTest extends TestCase
{
    use BuildsDomain;
    use RefreshDatabase;

    public function test_a_student_is_enrolled_in_the_circle_the_admin_picked(): void
    {
        $student = Student::factory()->create();
        $course = Course::factory()->create();
        $circle = Circle::factory()->create(['course_id' => $course->getKey()]);
        Circle::factory()->create(['course_id' => $course->getKey()]);

        $enrollment = $this->enroll($student, $course, $circle);

        $this->assertSame($circle->getKey(), $enrollment->circle_id);
        $this->assertSame($course->getKey(), $enrollment->course_id);
        $this->assertSame($student->getKey(), $enrollment->student_id);
    }

    public function test_the_circle_is_required(): void
    {
        $this->expectException(ValidationException::class);

        app(EnrollStudent::class)->handle([
            'student_id' => Student::factory()->create()->getKey(),
            'course_id' => Course::factory()->create()->getKey(),
            'enrolled_on' => now()->toDateString(),
        ]);
    }

    public function test_the_enrollment_date_cannot_be_in_the_future(): void
    {
        $course = Course::factory()->create();

        $this->expectException(ValidationException::class);

        $this->enroll(course: $course, enrolledOn: now()->addDay()->toDateString());
    }

    public function test_each_enrollment_gets_a_unique_numeric_card_code(): void
    {
        $course = Course::factory()->create();

        $first = $this->enroll(course: $course);
        $second = $this->enroll(course: $course);

        $this->assertMatchesRegularExpression('/^[0-9]{10}$/', $first->card_code);
        $this->assertMatchesRegularExpression('/^[0-9]{10}$/', $second->card_code);
        $this->assertNotSame($first->card_code, $second->card_code);
    }

    public function test_a_student_cannot_be_enrolled_twice_in_the_same_course(): void
    {
        $student = Student::factory()->create();
        $course = Course::factory()->create();
        $this->enroll($student, $course);

        $this->assertViolates('enrollment.duplicate', fn () => $this->enroll($student, $course));

        $this->assertSame(1, Enrollment::query()->count());
    }

    public function test_a_student_can_be_in_two_courses_with_a_card_for_each(): void
    {
        $student = Student::factory()->create();

        $first = $this->enroll($student, Course::factory()->create());
        $second = $this->enroll($student, Course::factory()->create());

        $this->assertNotSame($first->card_code, $second->card_code);
        $this->assertSame(2, $student->enrollments()->count());
    }

    public function test_the_circle_must_belong_to_the_course(): void
    {
        $course = Course::factory()->create();
        $circleOfAnotherCourse = Circle::factory()->create();

        $this->assertViolates('circle.other_course', fn () => app(EnrollStudent::class)->handle([
            'student_id' => Student::factory()->create()->getKey(),
            'course_id' => $course->getKey(),
            'circle_id' => $circleOfAnotherCourse->getKey(),
            'enrolled_on' => now()->toDateString(),
        ]));
    }

    public function test_an_inactive_circle_cannot_receive_students(): void
    {
        $circle = Circle::factory()->inactive()->create();

        $this->assertViolates('circle.inactive', fn () => $this->enroll(circle: $circle));
    }

    public function test_an_archived_student_cannot_be_enrolled(): void
    {
        $student = Student::factory()->archived()->create();

        $this->assertViolates('student.archived', fn () => $this->enroll($student));
    }

    public function test_a_finished_course_takes_no_new_students(): void
    {
        $course = Course::factory()->finished()->create();

        $this->assertViolates('course.finished', fn () => $this->enroll(course: $course));
    }

    public function test_an_enrollment_without_records_can_be_deleted(): void
    {
        $enrollment = $this->enroll();

        app(DeleteEnrollment::class)->handle($enrollment);

        $this->assertModelMissing($enrollment);
    }

    public function test_an_enrollment_with_records_cannot_be_deleted(): void
    {
        $enrollment = $this->enroll();
        $this->recite($enrollment, 1, 2);

        $this->assertViolates('enrollment.has_records', fn () => app(DeleteEnrollment::class)->handle($enrollment));
    }

    public function test_the_leaving_date_can_be_recorded_and_cleared(): void
    {
        $enrollment = $this->enroll();
        $enrolledOn = $enrollment->enrolled_on->toDateString();

        app(UpdateEnrollmentDates::class)->handle($enrollment, [
            'enrolled_on' => $enrolledOn,
            'left_on' => now()->toDateString(),
        ]);
        $this->assertTrue($enrollment->fresh()->hasLeft());
        $this->assertSame(0, Enrollment::query()->active()->count());

        app(UpdateEnrollmentDates::class)->handle($enrollment, ['enrolled_on' => $enrolledOn, 'left_on' => null]);
        $this->assertFalse($enrollment->fresh()->hasLeft());
    }

    public function test_enrollment_dates_cannot_leave_existing_records_outside(): void
    {
        $enrollment = $this->enroll();
        $this->recite($enrollment, 1, 2, now()->subDays(10)->toDateString());

        $this->assertViolates('enrollment.records_before_enrolled_on', fn () => app(UpdateEnrollmentDates::class)->handle(
            $enrollment,
            ['enrolled_on' => now()->subDays(5)->toDateString()],
        ));

        $this->assertViolates('enrollment.records_after_left_on', fn () => app(UpdateEnrollmentDates::class)->handle(
            $enrollment,
            ['enrolled_on' => $enrollment->enrolled_on->toDateString(), 'left_on' => now()->subDays(20)->toDateString()],
        ));
    }
}
