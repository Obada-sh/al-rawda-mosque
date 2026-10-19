<?php

namespace Tests\Feature;

use App\Actions\Attendance\RecordScanAttendance;
use App\Actions\Attendance\ScanIntoExceptionalSession;
use App\Actions\Attendance\ScanResult;
use App\Actions\Enrollments\UpdateEnrollmentDates;
use App\Actions\Students\ArchiveStudent;
use App\Enums\AttendanceMethod;
use App\Enums\ScanStatus;
use App\Enums\Weekday;
use App\Models\Attendance;
use App\Models\Course;
use App\Models\CourseSession;
use App\Models\PointTransaction;
use App\Support\Dates;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsDomain;
use Tests\TestCase;

class ScanAttendanceTest extends TestCase
{
    use BuildsDomain;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // A Wednesday afternoon.
        $this->travelTo('2026-10-07 17:00:00');
    }

    private function scan(?string $cardCode): ScanResult
    {
        return app(RecordScanAttendance::class)->handle($cardCode);
    }

    public function test_scanning_a_card_records_todays_attendance_and_awards_points(): void
    {
        $course = Course::factory()->create(['points_per_attendance' => 3]);
        $enrollment = $this->enroll(course: $course);

        $result = $this->scan($enrollment->card_code);

        $this->assertSame(ScanStatus::Recorded, $result->status);
        $this->assertSame($enrollment->student->full_name, $result->studentName());
        $this->assertSame($enrollment->circle->name, $result->circleName());

        $attendance = Attendance::query()->sole();
        $this->assertSame($enrollment->getKey(), $attendance->enrollment_id);
        $this->assertSame(AttendanceMethod::Qr, $attendance->method);
        $this->assertSame('2026-10-07 17:00:00', $attendance->scanned_at->toDateTimeString());
        $this->assertSame('2026-10-07', $attendance->session->session_date->toDateString());

        $points = PointTransaction::query()->sole();
        $this->assertSame(3, $points->points);
        $this->assertSame($attendance->getKey(), $points->attendance_id);
        $this->assertSame($enrollment->getKey(), $points->enrollment_id);
        $this->assertSame('2026-10-07', $points->occurred_on->toDateString());
    }

    public function test_a_second_scan_on_the_same_day_changes_nothing(): void
    {
        $enrollment = $this->enroll();

        $this->scan($enrollment->card_code);
        $result = $this->scan($enrollment->card_code);

        $this->assertSame(ScanStatus::AlreadyRecorded, $result->status);
        $this->assertTrue($result->status->isWarning());
        $this->assertSame(1, Attendance::query()->count());
        $this->assertSame(1, PointTransaction::query()->count());
    }

    public function test_all_students_scanned_on_a_day_share_one_session(): void
    {
        $course = Course::factory()->create();

        $this->scan($this->enroll(course: $course)->card_code);
        $this->scan($this->enroll(course: $course)->card_code);

        $this->assertSame(1, CourseSession::query()->count());
        $this->assertSame(2, Attendance::query()->count());
    }

    public function test_a_card_typed_in_arabic_digits_is_understood(): void
    {
        $enrollment = $this->enroll();
        $arabicDigits = strtr($enrollment->card_code, ['0' => '٠', '1' => '١', '2' => '٢', '3' => '٣', '4' => '٤', '5' => '٥', '6' => '٦', '7' => '٧', '8' => '٨', '9' => '٩']);

        $this->assertSame(ScanStatus::Recorded, $this->scan($arabicDigits)->status);
    }

    public function test_an_unknown_or_malformed_card_is_reported_without_an_exception(): void
    {
        $this->enroll();

        foreach (['1234567890123', 'not-a-card', '', null, '12'] as $cardCode) {
            $result = $this->scan($cardCode);

            $this->assertSame(ScanStatus::UnknownCard, $result->status);
            $this->assertTrue($result->status->isError());
        }

        $this->assertSame(0, Attendance::query()->count());
    }

    public function test_no_attendance_points_when_the_course_gives_none(): void
    {
        $enrollment = $this->enroll(course: Course::factory()->create(['points_per_attendance' => 0]));

        $this->assertSame(ScanStatus::Recorded, $this->scan($enrollment->card_code)->status);
        $this->assertSame(0, PointTransaction::query()->count());
    }

    public function test_a_scan_on_a_day_without_class_is_refused_and_opens_no_session(): void
    {
        $course = Course::factory()->meetingOn([Weekday::Friday])->create();
        $enrollment = $this->enroll(course: $course);

        $result = $this->scan($enrollment->card_code);

        $this->assertSame(ScanStatus::NotScheduledDay, $result->status);
        $this->assertSame(0, CourseSession::query()->count());
        $this->assertSame(0, Attendance::query()->count());
    }

    public function test_a_scan_is_accepted_on_an_extra_session_outside_the_timetable(): void
    {
        $course = Course::factory()->meetingOn([Weekday::Friday])->create();
        $enrollment = $this->enroll(course: $course);
        CourseSession::factory()->create(['course_id' => $course->getKey(), 'session_date' => '2026-10-07']);

        $this->assertSame(ScanStatus::Recorded, $this->scan($enrollment->card_code)->status);
    }

    public function test_a_card_refused_on_a_day_without_class_is_admitted_by_an_exceptional_session(): void
    {
        $course = Course::factory()->meetingOn([Weekday::Friday])->create(['points_per_attendance' => 2]);
        $enrollment = $this->enroll(course: $course);

        $result = app(ScanIntoExceptionalSession::class)->handle($enrollment->card_code);

        $this->assertSame(ScanStatus::Recorded, $result->status);
        $this->assertSame('2026-10-07', CourseSession::query()->sole()->session_date->toDateString());
        $this->assertSame(AttendanceMethod::Qr, Attendance::query()->sole()->method);
        $this->assertSame(2, PointTransaction::query()->sole()->points);

        // The session is now open: the same card again is simply already recorded.
        $this->assertSame(ScanStatus::AlreadyRecorded, app(ScanIntoExceptionalSession::class)->handle($enrollment->card_code)->status);
        $this->assertSame(1, CourseSession::query()->count());
    }

    public function test_an_exceptional_session_is_not_opened_for_any_other_refusal(): void
    {
        $cancelled = $this->enroll();
        CourseSession::factory()->cancelled()->create(['course_id' => $cancelled->course_id, 'session_date' => '2026-10-07']);

        $archived = $this->enroll(course: Course::factory()->meetingOn([Weekday::Friday])->create());
        app(ArchiveStudent::class)->handle($archived->student);

        $open = app(ScanIntoExceptionalSession::class);

        $this->assertSame(ScanStatus::UnknownCard, $open->handle('0000000000')->status);
        $this->assertSame(ScanStatus::SessionCancelled, $open->handle($cancelled->card_code)->status);
        $this->assertSame(ScanStatus::InactiveEnrollment, $open->handle($archived->card_code)->status);

        $this->assertSame(1, CourseSession::query()->count());
        $this->assertSame(0, Attendance::query()->count());
    }

    public function test_a_scan_on_a_cancelled_session_is_refused(): void
    {
        $course = Course::factory()->create();
        $enrollment = $this->enroll(course: $course);
        CourseSession::factory()->cancelled()->create(['course_id' => $course->getKey(), 'session_date' => '2026-10-07']);

        $this->assertSame(ScanStatus::SessionCancelled, $this->scan($enrollment->card_code)->status);
        $this->assertSame(0, Attendance::query()->count());
    }

    public function test_a_scan_is_refused_when_the_course_is_not_running(): void
    {
        $finished = $this->enroll(course: Course::factory()->create());
        $finished->course->update(['status' => 'finished']);

        $ended = $this->enroll(course: Course::factory()->create());
        $ended->course->update(['end_date' => '2026-10-01']);

        $notStarted = Course::factory()->create(['start_date' => '2026-10-20']);
        $early = $this->enroll(course: $notStarted, enrolledOn: '2026-10-07');

        foreach ([$finished, $ended, $early] as $enrollment) {
            $this->assertSame(ScanStatus::CourseNotRunning, $this->scan($enrollment->card_code)->status);
        }

        $this->assertSame(0, Attendance::query()->count());
    }

    public function test_a_scan_is_refused_for_a_student_who_left_or_is_archived(): void
    {
        $left = $this->enroll();
        app(UpdateEnrollmentDates::class)->handle($left, [
            'enrolled_on' => $left->enrolled_on->toDateString(),
            'left_on' => '2026-10-06',
        ]);

        $archived = $this->enroll();
        app(ArchiveStudent::class)->handle($archived->student);

        foreach ([$left, $archived] as $enrollment) {
            $this->assertSame(ScanStatus::InactiveEnrollment, $this->scan($enrollment->card_code)->status);
        }

        $this->assertSame(0, Attendance::query()->count());
    }

    public function test_a_card_can_be_recorded_for_an_earlier_day(): void
    {
        $course = Course::factory()->meetingOn([Weekday::Monday, Weekday::Wednesday])->create(['points_per_attendance' => 2]);
        $enrollment = $this->enroll(course: $course);
        $monday = Dates::parse('2026-10-05');

        $result = app(RecordScanAttendance::class)->handle($enrollment->card_code, day: $monday);

        $this->assertSame(ScanStatus::Recorded, $result->status);
        $this->assertSame('تم تسجيل الحضور ليوم 05/10/2026', $result->message());

        // The attendance and its points are Monday's; the card was read today.
        $attendance = Attendance::query()->sole();
        $this->assertSame('2026-10-05', $attendance->session->session_date->toDateString());
        $this->assertSame('2026-10-07 17:00:00', $attendance->scanned_at->toDateTimeString());
        $this->assertSame(AttendanceMethod::Qr, $attendance->method);
        $this->assertSame('2026-10-05', PointTransaction::query()->sole()->occurred_on->toDateString());

        $again = app(RecordScanAttendance::class)->handle($enrollment->card_code, day: $monday);
        $this->assertSame(ScanStatus::AlreadyRecorded, $again->status);
        $this->assertSame('الحضور مسجَّل مسبقاً يوم 05/10/2026', $again->message());

        // Today is another day: the card is recorded again, for today, in the words of today.
        $today = $this->scan($enrollment->card_code);
        $this->assertSame('تم تسجيل الحضور', $today->message());
        $this->assertSame(2, Attendance::query()->count());
    }

    public function test_an_earlier_day_follows_the_rules_of_its_own_date(): void
    {
        $course = Course::factory()->meetingOn([Weekday::Monday, Weekday::Wednesday])->create(['start_date' => '2026-09-01']);
        $late = $this->enroll(course: $course, enrolledOn: '2026-10-05');
        $left = $this->enroll(course: $course, enrolledOn: '2026-09-01');
        app(UpdateEnrollmentDates::class)->handle($left, ['enrolled_on' => '2026-09-01', 'left_on' => '2026-10-05']);

        $scan = fn ($enrollment, string $day): ScanResult => app(RecordScanAttendance::class)->handle($enrollment->card_code, day: Dates::parse($day));

        // Tuesday is not a class day; a day before the course or before the student joined is refused.
        $tuesday = $scan($late, '2026-10-06');
        $this->assertSame(ScanStatus::NotScheduledDay, $tuesday->status);
        $this->assertSame('يوم 06/10/2026 ليس يوم دوام لهذه الدورة', $tuesday->message());
        $this->assertSame(ScanStatus::CourseNotRunning, $scan($late, '2026-08-31')->status);
        $this->assertSame(ScanStatus::BeforeEnrollment, $scan($late, '2026-09-28')->status);
        $this->assertSame('يوم 28/09/2026 قبل تسجيل الطالب في الدورة', $scan($late, '2026-09-28')->message());

        // A student who left is still recorded for a day he was in the course.
        $this->assertSame(ScanStatus::Recorded, $scan($left, '2026-10-05')->status);
        $this->assertSame(ScanStatus::InactiveEnrollment, $this->scan($left->card_code)->status);

        $this->assertSame(['2026-10-05'], CourseSession::query()->pluck('session_date')->map->toDateString()->all());
    }

    public function test_an_exceptional_session_is_opened_on_the_earlier_day_of_the_card(): void
    {
        $course = Course::factory()->meetingOn([Weekday::Friday])->create();
        $enrollment = $this->enroll(course: $course);

        $result = app(ScanIntoExceptionalSession::class)->handle($enrollment->card_code, day: Dates::parse('2026-10-05'));

        $this->assertSame(ScanStatus::Recorded, $result->status);
        $this->assertSame('2026-10-05', CourseSession::query()->sole()->session_date->toDateString());
        $this->assertSame('2026-10-05', Attendance::query()->sole()->session->session_date->toDateString());
    }
}
