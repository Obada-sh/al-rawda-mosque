<?php

namespace Tests\Feature;

use App\Actions\Attendance\CancelSession;
use App\Actions\Attendance\DeleteAttendance;
use App\Actions\Attendance\OpenSession;
use App\Actions\Attendance\RecordManualAttendance;
use App\Actions\Attendance\RecordScanAttendance;
use App\Actions\Attendance\ReopenSession;
use App\Enums\AttendanceMethod;
use App\Enums\SessionStatus;
use App\Enums\Weekday;
use App\Models\Attendance;
use App\Models\Course;
use App\Models\CourseSession;
use App\Models\Enrollment;
use App\Models\PointTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\BuildsDomain;
use Tests\TestCase;

class ManualAttendanceTest extends TestCase
{
    use BuildsDomain;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // A Wednesday afternoon.
        $this->travelTo('2026-10-07 17:00:00');
    }

    private function attend(Enrollment $enrollment, string $date): Attendance
    {
        return app(RecordManualAttendance::class)->handle([
            'enrollment_id' => $enrollment->getKey(),
            'attended_on' => $date,
        ]);
    }

    public function test_the_admin_can_add_attendance_for_an_earlier_date(): void
    {
        $enrollment = $this->enroll(course: Course::factory()->create(['points_per_attendance' => 2]));

        $attendance = $this->attend($enrollment, '2026-09-20');

        $this->assertSame(AttendanceMethod::Manual, $attendance->method);
        $this->assertNull($attendance->scanned_at);
        $this->assertSame('2026-09-20', $attendance->session->session_date->toDateString());

        $points = PointTransaction::query()->sole();
        $this->assertSame(2, $points->points);
        $this->assertSame('2026-09-20', $points->occurred_on->toDateString());
    }

    public function test_manual_attendance_opens_a_session_even_on_a_day_without_class(): void
    {
        $course = Course::factory()->meetingOn([Weekday::Friday])->create();
        $enrollment = $this->enroll(course: $course);

        // 2026-10-06 is a Tuesday.
        $this->attend($enrollment, '2026-10-06');

        $session = CourseSession::query()->sole();
        $this->assertSame('2026-10-06', $session->session_date->toDateString());
        $this->assertSame(SessionStatus::Held, $session->status);
    }

    public function test_manual_and_scanned_attendance_cannot_both_exist_for_one_day(): void
    {
        $enrollment = $this->enroll();
        app(RecordScanAttendance::class)->handle($enrollment->card_code);

        $this->assertViolates('attendance.duplicate', fn () => $this->attend($enrollment, '2026-10-07'));

        $this->assertSame(1, Attendance::query()->count());
    }

    public function test_attendance_cannot_be_dated_in_the_future(): void
    {
        $enrollment = $this->enroll();

        $this->expectException(ValidationException::class);

        $this->attend($enrollment, '2026-10-08');
    }

    public function test_attendance_must_fall_inside_the_course_and_the_enrollment(): void
    {
        $course = Course::factory()->create(['start_date' => '2026-09-01', 'end_date' => '2026-10-05']);
        $enrollment = $this->enroll(course: $course, enrolledOn: '2026-09-10');

        $this->assertViolates('date.before_course_start', fn () => $this->attend($enrollment, '2026-08-31'));
        $this->assertViolates('date.before_enrollment', fn () => $this->attend($enrollment, '2026-09-05'));
        $this->assertViolates('date.after_course_end', fn () => $this->attend($enrollment, '2026-10-06'));

        $this->assertSame(0, Attendance::query()->count());
    }

    public function test_attendance_cannot_be_added_to_a_cancelled_session(): void
    {
        $course = Course::factory()->create();
        $enrollment = $this->enroll(course: $course);
        CourseSession::factory()->cancelled()->create(['course_id' => $course->getKey(), 'session_date' => '2026-10-01']);

        $this->assertViolates('session.cancelled', fn () => $this->attend($enrollment, '2026-10-01'));
    }

    public function test_deleting_an_attendance_removes_its_points(): void
    {
        $enrollment = $this->enroll();
        $attendance = $this->attend($enrollment, '2026-10-01');
        $this->assertSame(1, PointTransaction::query()->count());

        app(DeleteAttendance::class)->handle($attendance);

        $this->assertSame(0, Attendance::query()->count());
        $this->assertSame(0, PointTransaction::query()->count());
    }

    public function test_a_session_with_attendance_cannot_be_cancelled(): void
    {
        $enrollment = $this->enroll();
        $attendance = $this->attend($enrollment, '2026-10-01');

        $this->assertViolates('session.has_attendance', fn () => app(CancelSession::class)->handle($attendance->session));
    }

    public function test_an_empty_session_can_be_cancelled_and_reopened(): void
    {
        $course = Course::factory()->create();
        $session = app(OpenSession::class)->handle($course, ['session_date' => '2026-10-02']);

        app(CancelSession::class)->handle($session);
        $this->assertSame(SessionStatus::Cancelled, $session->fresh()->status);

        app(ReopenSession::class)->handle($session);
        $this->assertSame(SessionStatus::Held, $session->fresh()->status);
    }

    public function test_opening_a_session_reopens_a_cancelled_one_and_refuses_duplicates(): void
    {
        $course = Course::factory()->create();
        $cancelled = CourseSession::factory()->cancelled()->create(['course_id' => $course->getKey(), 'session_date' => '2026-10-02']);

        $reopened = app(OpenSession::class)->handle($course, ['session_date' => '2026-10-02']);

        $this->assertTrue($reopened->is($cancelled));
        $this->assertSame(SessionStatus::Held, $reopened->status);
        $this->assertViolates('session.exists', fn () => app(OpenSession::class)->handle($course, ['session_date' => '2026-10-02']));
        $this->assertSame(1, CourseSession::query()->count());
    }

    public function test_a_session_cannot_be_opened_outside_the_course_dates(): void
    {
        $course = Course::factory()->create(['start_date' => '2026-09-01']);

        $this->assertViolates('session.outside_course', fn () => app(OpenSession::class)->handle($course, ['session_date' => '2026-08-20']));
    }
}
