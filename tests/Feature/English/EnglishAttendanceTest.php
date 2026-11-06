<?php

namespace Tests\Feature\English;

use App\Actions\Attendance\ScanCard;
use App\Actions\English\Attendance\CancelEnglishSession;
use App\Actions\English\Attendance\RecordEnglishBulkAttendance;
use App\Actions\English\Attendance\RecordEnglishManualAttendance;
use App\Actions\English\Attendance\RecordEnglishScan;
use App\Actions\English\Attendance\ReopenEnglishSession;
use App\Actions\English\Attendance\ScanEnglishIntoExceptionalSession;
use App\Actions\English\Enrollments\UpdateEnglishEnrollmentDates;
use App\Enums\AttendanceMethod;
use App\Enums\ScanStatus;
use App\Enums\SessionStatus;
use App\Enums\Weekday;
use App\Models\Attendance;
use App\Models\CourseSession;
use App\Models\English\EnglishAttendance;
use App\Models\English\EnglishCourse;
use App\Models\English\EnglishSession;
use App\Support\Dates;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsDomain;
use Tests\Concerns\BuildsEnglish;
use Tests\TestCase;

/**
 * Attendance in the English section: the mosque's rules, on the English
 * section's own tables. Nothing recorded here reaches the mosque's.
 */
class EnglishAttendanceTest extends TestCase
{
    use BuildsDomain;
    use BuildsEnglish;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // A Wednesday.
        $this->travelTo('2026-10-07 17:00:00');
    }

    public function test_a_card_is_scanned_once_a_day(): void
    {
        $enrollment = $this->enrollInEnglish();

        $first = app(RecordEnglishScan::class)->handle($enrollment->card_code);
        $second = app(RecordEnglishScan::class)->handle($enrollment->card_code);

        $this->assertSame(ScanStatus::Recorded, $first->status);
        $this->assertSame(AttendanceMethod::Qr, $first->attendance->method);
        $this->assertSame($enrollment->student->full_name, $first->studentName());
        $this->assertSame($enrollment->hall->name, $first->hallName());
        $this->assertSame(ScanStatus::AlreadyRecorded, $second->status);
        $this->assertSame(1, EnglishAttendance::query()->count());
        $this->assertSame('2026-10-07', EnglishSession::query()->sole()->session_date->toDateString());

        // The mosque's tables are untouched.
        $this->assertSame(0, Attendance::query()->count());
        $this->assertSame(0, CourseSession::query()->count());
    }

    public function test_cards_of_one_section_are_recognised_at_the_other_desk(): void
    {
        $english = $this->enrollInEnglish();
        $mosque = $this->enroll();

        $this->assertSame(ScanStatus::MosqueCard, app(RecordEnglishScan::class)->handle($mosque->card_code)->status);
        $this->assertSame(ScanStatus::EnglishCard, app(ScanCard::class)->handle($english->card_code)->status);
        $this->assertSame(ScanStatus::UnknownCard, app(RecordEnglishScan::class)->handle('1234567890')->status);
        $this->assertSame(ScanStatus::UnknownCard, app(RecordEnglishScan::class)->handle('abc')->status);

        $this->assertSame(0, EnglishAttendance::query()->count());
        $this->assertSame(0, Attendance::query()->count());
    }

    public function test_the_mosque_and_english_card_codes_never_meet(): void
    {
        $codes = collect(range(1, 15))->map(fn (): string => $this->enrollInEnglish()->card_code)
            ->merge(collect(range(1, 15))->map(fn (): string => $this->enroll()->card_code));

        $this->assertSame(30, $codes->unique()->count());
    }

    public function test_the_rules_of_the_day(): void
    {
        $left = $this->enrollInEnglish(enrolledOn: '2026-09-01');
        app(UpdateEnglishEnrollmentDates::class)->handle($left, ['enrolled_on' => '2026-09-01', 'left_on' => '2026-10-01']);
        $finished = $this->enrollInEnglish(course: EnglishCourse::factory()->create());
        $finished->course->update(['status' => 'finished']);
        $notToday = $this->enrollInEnglish(course: EnglishCourse::factory()->meetingOn([Weekday::Saturday])->create());
        $later = $this->enrollInEnglish(enrolledOn: '2026-10-07');

        $this->assertSame(ScanStatus::InactiveEnrollment, app(RecordEnglishScan::class)->handle($left->card_code)->status);
        $this->assertSame(ScanStatus::CourseNotRunning, app(RecordEnglishScan::class)->handle($finished->card_code)->status);
        $this->assertSame(ScanStatus::NotScheduledDay, app(RecordEnglishScan::class)->handle($notToday->card_code)->status);
        $this->assertSame(ScanStatus::BeforeEnrollment, app(RecordEnglishScan::class)->handle($later->card_code, day: Dates::parse('2026-10-06'))->status);
        $this->assertSame(ScanStatus::Recorded, app(RecordEnglishScan::class)->handle($later->card_code)->status);
    }

    public function test_an_exceptional_session_is_opened_for_a_card_refused_for_its_day(): void
    {
        $enrollment = $this->enrollInEnglish(course: EnglishCourse::factory()->meetingOn([Weekday::Saturday])->create());

        $result = app(ScanEnglishIntoExceptionalSession::class)->handle($enrollment->card_code);

        $this->assertSame(ScanStatus::Recorded, $result->status);
        $this->assertSame(1, $enrollment->course->sessions()->count());
    }

    public function test_a_cancelled_day_refuses_cards_until_it_is_reopened(): void
    {
        $enrollment = $this->enrollInEnglish();
        $session = EnglishSession::factory()->cancelled()->create(['english_course_id' => $enrollment->english_course_id, 'session_date' => '2026-10-07']);

        $this->assertSame(ScanStatus::SessionCancelled, app(RecordEnglishScan::class)->handle($enrollment->card_code)->status);

        app(ReopenEnglishSession::class)->handle($session);
        $this->assertSame(ScanStatus::Recorded, app(RecordEnglishScan::class)->handle($enrollment->card_code)->status);

        $this->assertViolates('session.has_attendance', fn () => app(CancelEnglishSession::class)->handle($session->fresh()));
        $this->assertSame(SessionStatus::Held, $session->fresh()->status);
    }

    public function test_manual_attendance_follows_the_dates_of_the_course_and_the_student(): void
    {
        $enrollment = $this->enrollInEnglish(enrolledOn: '2026-09-20');

        $attendance = app(RecordEnglishManualAttendance::class)->handle(['english_enrollment_id' => $enrollment->getKey(), 'attended_on' => '2026-10-03']);

        $this->assertSame(AttendanceMethod::Manual, $attendance->method);
        $this->assertNull($attendance->scanned_at);
        $this->assertViolates('attendance.duplicate', fn () => app(RecordEnglishManualAttendance::class)->handle(['english_enrollment_id' => $enrollment->getKey(), 'attended_on' => '2026-10-03']));
        $this->assertViolates('date.before_enrollment', fn () => app(RecordEnglishManualAttendance::class)->handle(['english_enrollment_id' => $enrollment->getKey(), 'attended_on' => '2026-09-19']));
    }

    public function test_bulk_attendance_by_name_and_by_card(): void
    {
        $course = EnglishCourse::factory()->create();
        $first = $this->enrollInEnglish(course: $course);
        $second = $this->enrollInEnglish(course: $course);
        $otherCourse = $this->enrollInEnglish();
        $mosque = $this->enroll();

        $result = app(RecordEnglishBulkAttendance::class)->handle([
            'english_course_id' => $course->getKey(),
            'attended_on' => '2026-10-07',
            'english_enrollment_ids' => [$first->getKey()],
            'card_codes' => "{$second->card_code}\n{$first->card_code} {$otherCourse->card_code}, {$mosque->card_code} 12",
        ]);

        $this->assertEqualsCanonicalizing([$first->student->full_name, $second->student->full_name], $result->recorded);
        $this->assertCount(2, $result->notInCourse);
        $this->assertStringContainsString('بطاقة من دورات المسجد', implode(' ', $result->notInCourse));
        $this->assertSame(['12'], $result->unknownCodes);
        $this->assertSame(2, EnglishAttendance::query()->count());
        $this->assertSame(0, Attendance::query()->count());
    }
}
