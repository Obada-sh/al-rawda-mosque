<?php

namespace Tests\Feature;

use App\Actions\Attendance\CancelSession;
use App\Actions\Attendance\DeleteTeacherAttendance;
use App\Actions\Attendance\RecordManualTeacherAttendance;
use App\Actions\Attendance\ScanCard;
use App\Actions\Attendance\ScanIntoExceptionalSession;
use App\Actions\Attendance\ScanResult;
use App\Actions\Cards\PrepareTeacherCards;
use App\Actions\Teachers\DeleteTeacher;
use App\Actions\Teachers\SaveTeacher;
use App\Enums\AttendanceMethod;
use App\Enums\ScanStatus;
use App\Enums\Weekday;
use App\Models\Attendance;
use App\Models\Circle;
use App\Models\Course;
use App\Models\CourseSession;
use App\Models\Teacher;
use App\Models\TeacherAttendance;
use App\Services\Statistics\DashboardFigures;
use App\Services\Statistics\ReportFilter;
use App\Services\Statistics\TeacherAttendanceStandings;
use App\Services\Teachers\TeacherCards;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\BuildsDomain;
use Tests\TestCase;

/**
 * The attendance of the teachers: the supervisor and the assistant of a
 * circle scan their card of the course at the same desk as the students,
 * or are marked present by hand.
 */
class TeacherAttendanceTest extends TestCase
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
        return app(ScanCard::class)->handle($cardCode);
    }

    /** The code of the teacher's card of the course. */
    private function card(Teacher $teacher, Course $course): string
    {
        return app(TeacherCards::class)->of($teacher, $course)->card_code;
    }

    private function teacher(string $name = 'الشيخ ياسر'): Teacher
    {
        return app(SaveTeacher::class)->handle(['full_name' => $name]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function circle(Course $course, array $attributes = []): Circle
    {
        return Circle::factory()->create(['course_id' => $course->getKey()] + $attributes);
    }

    private function manual(Teacher $teacher, Course $course, string $day = '2026-10-07'): TeacherAttendance
    {
        return app(RecordManualTeacherAttendance::class)->handle([
            'teacher_id' => $teacher->getKey(),
            'course_id' => $course->getKey(),
            'attended_on' => $day,
        ]);
    }

    public function test_a_teacher_has_a_card_of_their_own_in_each_course(): void
    {
        $student = $this->enroll();
        $teacher = $this->teacher();
        $autumn = Course::factory()->create();
        $spring = Course::factory()->create();

        $code = $this->card($teacher, $autumn);

        $this->assertMatchesRegularExpression('/^[0-9]{10}$/', $code);
        $this->assertNotSame($student->card_code, $code);
        // The card of another course is another card; the same course always gives the same card.
        $this->assertNotSame($code, $this->card($teacher, $spring));
        $this->assertSame($code, $this->card($teacher, $autumn));
        $this->assertSame(2, $teacher->cards()->count());
    }

    public function test_the_cards_of_a_course_are_for_its_supervisors_and_assistants_without_a_circle(): void
    {
        $course = Course::factory()->create();
        $supervisor = $this->teacher('الشيخ ياسر');
        $assistant = $this->teacher('الأستاذ بلال');
        $ofInactiveCircle = $this->teacher('الأستاذ سامر');
        $ofAnotherCourse = $this->teacher('الأستاذ عمر');
        $this->circle($course, ['supervisor_teacher_id' => $supervisor->getKey(), 'assistant_teacher_id' => $assistant->getKey()]);
        $this->circle($course, ['supervisor_teacher_id' => $ofInactiveCircle->getKey(), 'is_active' => false]);
        $this->circle(Course::factory()->create(), ['supervisor_teacher_id' => $ofAnotherCourse->getKey()]);

        $cards = app(PrepareTeacherCards::class)->handle(['course_id' => $course->getKey()]);

        $this->assertEqualsCanonicalizing(['الشيخ ياسر', 'الأستاذ بلال'], $cards->pluck('name')->all());
        $this->assertTrue($cards->every(fn ($card): bool => $card->isTeacher() && $card->circleName === '' && $card->circleLogo === null));
        $this->assertSame([$course->name], $cards->pluck('courseName')->unique()->values()->all());
        $this->assertSame($this->card($supervisor, $course), $cards->firstWhere('name', 'الشيخ ياسر')->code);

        // Chosen teachers only, and only those of the course.
        $chosen = app(PrepareTeacherCards::class)->handle([
            'course_id' => $course->getKey(),
            'teacher_ids' => [$assistant->getKey(), $ofAnotherCourse->getKey()],
        ]);
        $this->assertSame(['الأستاذ بلال'], $chosen->pluck('name')->all());
    }

    public function test_the_supervisors_card_records_todays_attendance_in_the_course_of_the_circle(): void
    {
        $teacher = $this->teacher();
        $course = Course::factory()->create(['name' => 'دورة الخريف']);
        $this->circle($course, ['name' => 'حلقة النور', 'supervisor_teacher_id' => $teacher->getKey()]);

        $result = $this->scan($this->card($teacher, $course));

        $this->assertSame(ScanStatus::Recorded, $result->status);
        $this->assertTrue($result->isTeacher());
        $this->assertSame('الشيخ ياسر', $result->name());
        $this->assertSame('مشرف حلقة النور', $result->circleName());
        $this->assertSame('دورة الخريف', $result->courseName());

        $attendance = TeacherAttendance::query()->sole();
        $this->assertSame($teacher->getKey(), $attendance->teacher_id);
        $this->assertSame(AttendanceMethod::Qr, $attendance->method);
        $this->assertSame('2026-10-07 17:00:00', $attendance->scanned_at->toDateTimeString());
        $this->assertSame('2026-10-07', $attendance->session->session_date->toDateString());
        $this->assertSame($course->getKey(), $attendance->session->course_id);

        // A teacher is not a student: no student attendance, no points.
        $this->assertSame(0, Attendance::query()->count());
    }

    public function test_the_assistants_card_records_their_attendance_too(): void
    {
        $assistant = $this->teacher('الأستاذ بلال');
        $course = Course::factory()->create();
        $this->circle($course, ['name' => 'حلقة الفجر', 'supervisor_teacher_id' => $this->teacher()->getKey(), 'assistant_teacher_id' => $assistant->getKey()]);

        $result = $this->scan($this->card($assistant, $course));

        $this->assertSame(ScanStatus::Recorded, $result->status);
        $this->assertSame('مساعد حلقة الفجر', $result->circleName());
        $this->assertSame($assistant->getKey(), TeacherAttendance::query()->sole()->teacher_id);
    }

    public function test_a_second_scan_on_the_same_day_changes_nothing(): void
    {
        $teacher = $this->teacher();
        $course = Course::factory()->create();
        $this->circle($course, ['supervisor_teacher_id' => $teacher->getKey()]);

        $this->scan($this->card($teacher, $course));
        $result = $this->scan($this->card($teacher, $course));

        $this->assertSame(ScanStatus::AlreadyRecorded, $result->status);
        $this->assertSame(1, TeacherAttendance::query()->count());
    }

    public function test_students_and_teachers_of_a_day_share_its_session(): void
    {
        $teacher = $this->teacher();
        $course = Course::factory()->create();
        $circle = $this->circle($course, ['supervisor_teacher_id' => $teacher->getKey()]);
        $enrollment = $this->enroll(course: $course, circle: $circle);

        $this->assertSame(ScanStatus::Recorded, $this->scan($enrollment->card_code)->status);
        $this->assertSame(ScanStatus::Recorded, $this->scan($this->card($teacher, $course))->status);

        $this->assertSame(1, CourseSession::query()->count());
        $this->assertSame(1, Attendance::query()->count());
        $this->assertSame(1, TeacherAttendance::query()->count());
    }

    public function test_a_card_counts_in_its_own_course_only(): void
    {
        $teacher = $this->teacher();
        $morning = Course::factory()->create(['name' => 'دورة الصباح']);
        $evening = Course::factory()->create(['name' => 'دورة المساء']);
        $this->circle($morning, ['name' => 'حلقة أ', 'supervisor_teacher_id' => $teacher->getKey()]);
        $this->circle($evening, ['name' => 'حلقة ب', 'assistant_teacher_id' => $teacher->getKey()]);

        $result = $this->scan($this->card($teacher, $morning));

        $this->assertSame(ScanStatus::Recorded, $result->status);
        $this->assertSame('مشرف حلقة أ', $result->circleName());
        $this->assertSame('دورة الصباح', $result->courseName());
        $this->assertSame($morning->getKey(), TeacherAttendance::query()->sole()->session->course_id);

        // The card of the other course records the other course.
        $evenings = $this->scan($this->card($teacher, $evening));
        $this->assertSame(ScanStatus::Recorded, $evenings->status);
        $this->assertSame('مساعد حلقة ب', $evenings->circleName());
        $this->assertSame(2, TeacherAttendance::query()->count());

        // ...and the teacher is counted once among those who came today.
        $this->assertSame(1, app(DashboardFigures::class)->teachersPresentToday());
        $this->assertSame(0, app(DashboardFigures::class)->attendanceToday());
    }

    public function test_the_card_of_an_inactive_teacher_is_refused(): void
    {
        $teacher = $this->teacher();
        $course = Course::factory()->create();
        $this->circle($course, ['supervisor_teacher_id' => $teacher->getKey()]);
        $code = $this->card($teacher, $course);
        app(SaveTeacher::class)->handle(['full_name' => $teacher->full_name, 'is_active' => false], $teacher);

        $result = $this->scan($code);

        $this->assertSame(ScanStatus::InactiveTeacher, $result->status);
        $this->assertTrue($result->status->isError());
        $this->assertSame(0, TeacherAttendance::query()->count());
    }

    public function test_a_card_whose_teacher_no_longer_teaches_in_the_course_or_whose_course_is_over_is_refused(): void
    {
        $course = Course::factory()->create();
        $removed = $this->teacher('الأستاذ خالد');
        $ofInactiveCircle = $this->teacher('الأستاذ سامر');
        $circle = $this->circle($course, ['supervisor_teacher_id' => $removed->getKey()]);
        $this->circle($course, ['supervisor_teacher_id' => $ofInactiveCircle->getKey(), 'is_active' => false]);

        // The card was printed, then the circle was given to someone else.
        $removedCard = $this->card($removed, $course);
        $circle->update(['supervisor_teacher_id' => null]);

        foreach ([$removedCard, $this->card($ofInactiveCircle, $course)] as $code) {
            $this->assertSame(ScanStatus::TeacherWithoutCircle, $this->scan($code)->status);
        }

        $finished = Course::factory()->finished()->create();
        $ofFinishedCourse = $this->teacher('الأستاذ عمر');
        $this->circle($finished, ['supervisor_teacher_id' => $ofFinishedCourse->getKey()]);

        $result = $this->scan($this->card($ofFinishedCourse, $finished));
        $this->assertSame(ScanStatus::CourseNotRunning, $result->status);
        $this->assertSame('الأستاذ عمر', $result->name());

        $this->assertSame(0, TeacherAttendance::query()->count());
        $this->assertSame(0, CourseSession::query()->count());
    }

    public function test_on_a_day_without_class_the_teacher_can_open_an_exceptional_session(): void
    {
        $teacher = $this->teacher();
        $course = Course::factory()->meetingOn([Weekday::Friday])->create();
        $this->circle($course, ['supervisor_teacher_id' => $teacher->getKey()]);

        $code = $this->card($teacher, $course);
        $refused = $this->scan($code);

        $this->assertSame(ScanStatus::NotScheduledDay, $refused->status);
        $this->assertSame(0, CourseSession::query()->count());

        $result = app(ScanIntoExceptionalSession::class)->handle($code);

        $this->assertSame(ScanStatus::Recorded, $result->status);
        $session = CourseSession::query()->sole();
        $this->assertSame('2026-10-07', $session->session_date->toDateString());
        $this->assertSame($session->getKey(), TeacherAttendance::query()->sole()->course_session_id);
    }

    public function test_a_cancelled_session_refuses_the_teachers_card(): void
    {
        $teacher = $this->teacher();
        $course = Course::factory()->create();
        $this->circle($course, ['supervisor_teacher_id' => $teacher->getKey()]);
        CourseSession::factory()->cancelled()->create(['course_id' => $course->getKey(), 'session_date' => '2026-10-07']);

        $this->assertSame(ScanStatus::SessionCancelled, $this->scan($this->card($teacher, $course))->status);
        $this->assertSame(0, TeacherAttendance::query()->count());
    }

    public function test_a_session_with_a_teachers_attendance_cannot_be_cancelled(): void
    {
        $teacher = $this->teacher();
        $course = Course::factory()->create();
        $this->circle($course, ['supervisor_teacher_id' => $teacher->getKey()]);
        $this->scan($this->card($teacher, $course));

        $session = CourseSession::query()->sole();

        $this->assertViolates('session.has_attendance', fn () => app(CancelSession::class)->handle($session));

        app(DeleteTeacherAttendance::class)->handle(TeacherAttendance::query()->sole());
        app(CancelSession::class)->handle($session);

        $this->assertTrue($session->fresh()->isCancelled());
    }

    public function test_a_teachers_attendance_can_be_added_by_hand_for_an_earlier_day(): void
    {
        $teacher = $this->teacher();
        $course = Course::factory()->create();
        $this->circle($course, ['supervisor_teacher_id' => $teacher->getKey()]);

        $attendance = $this->manual($teacher, $course, '2026-10-05');

        $this->assertSame(AttendanceMethod::Manual, $attendance->method);
        $this->assertNull($attendance->scanned_at);
        $this->assertSame('2026-10-05', $attendance->session->session_date->toDateString());

        // The same day again is refused; the card on that day too.
        $this->assertViolates('attendance.duplicate', fn () => $this->manual($teacher, $course, '2026-10-05'));
    }

    public function test_the_rules_of_the_attendance_added_by_hand(): void
    {
        $teacher = $this->teacher();
        $course = Course::factory()->create(['start_date' => '2026-09-01']);
        $this->circle($course, ['supervisor_teacher_id' => $teacher->getKey()]);
        $otherCourse = Course::factory()->create();

        // Only in a course in which the teacher supervises or assists a circle.
        $this->assertViolates('teacher.not_in_course', fn () => $this->manual($teacher, $otherCourse));

        // Only within the course.
        $this->assertViolates('session.outside_course', fn () => $this->manual($teacher, $course, '2026-08-20'));

        // Never in the future.
        try {
            $this->manual($teacher, $course, '2026-10-08');
            $this->fail('An attendance in the future was accepted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('attended_on', $exception->errors());
        }

        $this->assertSame(0, TeacherAttendance::query()->count());
    }

    public function test_a_teacher_with_attendance_cannot_be_deleted(): void
    {
        $teacher = $this->teacher();
        $course = Course::factory()->create();
        $circle = $this->circle($course, ['supervisor_teacher_id' => $teacher->getKey()]);
        $this->scan($this->card($teacher, $course));

        // Even once the circle has another supervisor, the attendance keeps the teacher.
        $circle->update(['supervisor_teacher_id' => null]);

        $this->assertViolates('teacher.has_records', fn () => app(DeleteTeacher::class)->handle($teacher));
    }

    public function test_the_report_gives_each_teacher_the_sessions_held_and_those_attended(): void
    {
        $regular = $this->teacher('الأستاذ المواظب');
        $absent = $this->teacher('الأستاذ الغائب');
        $course = Course::factory()->create();
        $this->circle($course, ['name' => 'حلقة النور', 'supervisor_teacher_id' => $regular->getKey(), 'assistant_teacher_id' => $absent->getKey()]);

        foreach (['2026-10-05', '2026-10-06', '2026-10-07'] as $day) {
            $this->manual($regular, $course, $day);
        }

        // A day on which only students came is still a session the teachers were expected at.
        CourseSession::factory()->create(['course_id' => $course->getKey(), 'session_date' => '2026-10-04']);
        $this->manual($absent, $course, '2026-10-07');

        $rows = app(TeacherAttendanceStandings::class)
            ->for(new ReportFilter(courseId: $course->getKey()))
            ->keyBy('teacherName');

        $this->assertSame(4, $rows['الأستاذ المواظب']->sessions);
        $this->assertSame(3, $rows['الأستاذ المواظب']->attended);
        $this->assertSame(75.0, $rows['الأستاذ المواظب']->rate());
        $this->assertSame('2026-10-07', $rows['الأستاذ المواظب']->lastAttendedOn);
        $this->assertSame('مشرف حلقة النور', $rows['الأستاذ المواظب']->roles);

        $this->assertSame(1, $rows['الأستاذ الغائب']->attended);
        $this->assertSame(3, $rows['الأستاذ الغائب']->absences());
        $this->assertSame('مساعد حلقة النور', $rows['الأستاذ الغائب']->roles);

        // Narrowed to a period.
        $period = app(TeacherAttendanceStandings::class)
            ->for(new ReportFilter(courseId: $course->getKey(), from: '2026-10-06'))
            ->keyBy('teacherName');

        $this->assertSame(2, $period['الأستاذ المواظب']->sessions);
        $this->assertSame(2, $period['الأستاذ المواظب']->attended);
    }
}
