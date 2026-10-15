<?php

namespace Tests\Feature\Panel;

use App\Contracts\AdminNotifier;
use App\Enums\AttendanceMethod;
use App\Enums\ExamGrade;
use App\Enums\ExamOrigin;
use App\Enums\ExamStatus;
use App\Enums\PointSource;
use App\Enums\Weekday;
use App\Filament\Pages\ScanAttendance;
use App\Filament\Resources\Attendances\Pages\ManageAttendances;
use App\Filament\Resources\JuzExams\Pages\ManageJuzExams;
use App\Filament\Resources\PointTransactions\Pages\ManagePointTransactions;
use App\Filament\Resources\Recitations\Pages\ManageRecitations;
use App\Models\Attendance;
use App\Models\Course;
use App\Models\JuzExam;
use App\Models\PointTransaction;
use App\Models\Recitation;
use App\Models\Student;
use App\Models\Teacher;
use Filament\Actions\Testing\TestAction;
use Livewire\Livewire;

/**
 * The screens used every day: scanning cards, the attendance register,
 * recitations, juz exams and points.
 */
class DailyWorkPanelTest extends PanelTestCase
{
    public function test_scanning_a_card_records_attendance_and_answers_on_screen(): void
    {
        $enrollment = $this->enroll(course: Course::factory()->create(['points_per_attendance' => 2]));

        $page = Livewire::test(ScanAttendance::class)
            ->assertOk()
            ->assertSee('بانتظار أول بطاقة')
            ->set('cardCode', $enrollment->card_code)
            ->call('scan')
            ->assertSet('cardCode', '')
            ->assertSet('result.tone', 'success')
            ->assertSee('تم تسجيل الحضور')
            ->assertSee($enrollment->student->full_name)
            ->assertSee($enrollment->circle->name)
            ->assertDispatched('scan-processed', tone: 'success');

        $attendance = Attendance::query()->sole();
        $this->assertSame(AttendanceMethod::Qr, $attendance->method);
        $this->assertSame(2, PointTransaction::query()->sole()->points);

        $page
            ->set('cardCode', $enrollment->card_code)
            ->call('scan')
            ->assertSet('result.tone', 'warning')
            ->assertSee('الحضور مسجَّل مسبقاً اليوم')
            ->assertDispatched('scan-processed', tone: 'warning');

        $this->assertSame(1, Attendance::query()->count());
    }

    public function test_the_scan_screen_explains_why_a_card_was_refused(): void
    {
        $notToday = $this->enroll(course: Course::factory()->meetingOn([Weekday::Friday])->create());

        Livewire::test(ScanAttendance::class)
            ->set('cardCode', '0000000000')
            ->call('scan')
            ->assertSet('result.tone', 'danger')
            ->assertSee('بطاقة غير معروفة')
            ->set('cardCode', $notToday->card_code)
            ->call('scan')
            ->assertSet('result.tone', 'danger')
            ->assertSee('اليوم ليس يوم دوام لهذه الدورة')
            ->assertSee($notToday->student->full_name)
            ->set('cardCode', '   ')
            ->call('scan')
            ->assertSet('result.message', 'اليوم ليس يوم دوام لهذه الدورة');

        $this->assertSame(0, Attendance::query()->count());
    }

    public function test_a_card_refused_on_a_day_off_is_admitted_by_opening_an_exceptional_session(): void
    {
        $enrollment = $this->enroll(course: Course::factory()->meetingOn([Weekday::Friday])->create());

        Livewire::test(ScanAttendance::class)
            ->assertDontSee('فتح جلسة استثنائية اليوم وتسجيل الحضور')
            ->set('cardCode', $enrollment->card_code)
            ->call('scan')
            ->assertSet('result.tone', 'danger')
            ->assertSet('refusedCard', $enrollment->card_code)
            ->assertSee('فتح جلسة استثنائية اليوم وتسجيل الحضور')
            ->call('openExceptionalSession')
            ->assertSet('result.tone', 'success')
            ->assertSet('refusedCard', null)
            ->assertSee('تم تسجيل الحضور')
            ->assertSee($enrollment->student->full_name)
            ->assertDontSee('فتح جلسة استثنائية اليوم وتسجيل الحضور')
            ->assertDispatched('scan-processed', tone: 'success');

        $attendance = Attendance::query()->sole();
        $this->assertSame(AttendanceMethod::Qr, $attendance->method);
        $this->assertSame('2026-10-07', $attendance->session->session_date->toDateString());

        // The session is open now: the next card of the course needs no button.
        $next = $this->enroll(course: $enrollment->course);

        Livewire::test(ScanAttendance::class)
            ->set('cardCode', $next->card_code)
            ->call('scan')
            ->assertSet('result.tone', 'success')
            ->assertSet('refusedCard', null);
    }

    public function test_the_exceptional_session_is_offered_only_for_a_day_off(): void
    {
        Livewire::test(ScanAttendance::class)
            ->set('cardCode', '0000000000')
            ->call('scan')
            ->assertSet('result.tone', 'danger')
            ->assertSet('refusedCard', null)
            ->assertDontSee('فتح جلسة استثنائية اليوم وتسجيل الحضور')
            ->call('openExceptionalSession')
            ->assertSet('result.message', 'بطاقة غير معروفة');

        $this->assertSame(0, Attendance::query()->count());
    }

    public function test_attendance_is_added_by_hand_for_any_past_day_and_can_be_deleted(): void
    {
        $enrollment = $this->enroll();

        Livewire::test(ManageAttendances::class)
            ->callAction('manual', data: ['enrollment_id' => $enrollment->getKey(), 'attended_on' => '2026-10-02'])
            ->assertHasNoActionErrors()
            ->assertNotified('تم تسجيل الحضور');

        $attendance = Attendance::query()->sole();
        $this->assertSame(AttendanceMethod::Manual, $attendance->method);
        $this->assertSame('2026-10-02', $attendance->session->session_date->toDateString());

        Livewire::test(ManageAttendances::class)
            ->callAction('manual', data: ['enrollment_id' => $enrollment->getKey(), 'attended_on' => '2026-10-02'])
            ->assertNotified('حضور الطالب مسجَّل مسبقاً في هذا التاريخ.');

        Livewire::test(ManageAttendances::class)
            ->callAction('manual', data: ['enrollment_id' => null])
            ->assertHasActionErrors(['enrollment_id']);

        Livewire::test(ManageAttendances::class)
            ->assertCanSeeTableRecords([$attendance])
            ->callAction(TestAction::make('delete')->table($attendance));

        $this->assertSame(0, Attendance::query()->count());
        $this->assertSame(0, PointTransaction::query()->count());
    }

    public function test_a_recitation_is_recorded_corrected_and_deleted(): void
    {
        $enrollment = $this->enroll(course: Course::factory()->create(['points_per_page' => 3]));
        $teacher = Teacher::factory()->create();

        Livewire::test(ManageRecitations::class)
            ->callAction('create', data: [
                'enrollment_id' => $enrollment->getKey(),
                'teacher_id' => $teacher->getKey(),
                'from_page' => '1',
                'to_page' => '5',
            ])
            ->assertHasNoActionErrors()
            ->assertNotified('تم تسجيل التسميع');

        $recitation = Recitation::query()->sole();
        $this->assertSame([1, 5, 5], [$recitation->from_page, $recitation->to_page, $recitation->new_pages_count]);
        $this->assertSame('2026-10-07', $recitation->recited_on->toDateString());
        $this->assertSame(15, PointTransaction::query()->sole()->points);
        $this->assertSame(5, $enrollment->student->fresh()->recited_pages_count);

        Livewire::test(ManageRecitations::class)
            ->assertCanSeeTableRecords([$recitation])
            ->mountAction(TestAction::make('edit')->table($recitation))
            ->assertSchemaStateSet(['from_page' => 1, 'to_page' => 5, 'recited_on' => '2026-10-07'])
            ->fillForm(['to_page' => '8', 'recited_on' => '2026-10-06'])
            ->callMountedAction()
            ->assertHasNoActionErrors();

        $recitation->refresh();
        $this->assertSame(8, $recitation->new_pages_count);
        $this->assertSame('2026-10-06', $recitation->recited_on->toDateString());
        $this->assertSame(24, PointTransaction::query()->sole()->points);

        Livewire::test(ManageRecitations::class)
            ->callAction(TestAction::make('delete')->table($recitation));

        $this->assertSame(0, Recitation::query()->count());
        $this->assertSame(0, $enrollment->student->fresh()->recited_pages_count);
    }

    public function test_recitation_validation_messages_appear_under_the_fields(): void
    {
        $enrollment = $this->enroll();
        $teacher = Teacher::factory()->create();

        Livewire::test(ManageRecitations::class)
            ->callAction('create', data: [
                'enrollment_id' => $enrollment->getKey(),
                'teacher_id' => $teacher->getKey(),
                'from_page' => '10',
                'to_page' => '9',
            ])
            ->assertHasActionErrors(['to_page']);

        Livewire::test(ManageRecitations::class)
            ->callAction('create', data: [
                'enrollment_id' => $enrollment->getKey(),
                'teacher_id' => $teacher->getKey(),
                'from_page' => '600',
                'to_page' => '605',
            ])
            ->assertHasActionErrors(['to_page']);

        $this->assertSame(0, Recitation::query()->count());
    }

    public function test_completing_a_juz_alerts_the_admin_and_the_exam_result_is_recorded(): void
    {
        // The real notifier, not the recording double used by the other tests.
        $this->app->forgetInstance(AdminNotifier::class);

        $enrollment = $this->enroll();
        $teacher = Teacher::factory()->create();

        Livewire::test(ManageRecitations::class)
            ->callAction('create', data: [
                'enrollment_id' => $enrollment->getKey(),
                'teacher_id' => $teacher->getKey(),
                'from_page' => '582',
                'to_page' => '604',
            ])
            ->assertHasNoActionErrors()
            ->assertNotified('سبر مطلوب');

        $exam = JuzExam::query()->sole();
        $this->assertSame([30, ExamStatus::Pending, ExamOrigin::Auto], [$exam->juz_no, $exam->status, $exam->origin]);
        $this->assertSame(1, $this->admin->notifications()->count());

        Livewire::test(ManageJuzExams::class)
            ->assertCanSeeTableRecords([$exam])
            ->callAction(TestAction::make('result')->table($exam), data: ['examiner_teacher_id' => $teacher->getKey()])
            ->assertHasActionErrors(['grade']);

        Livewire::test(ManageJuzExams::class)
            ->callAction(TestAction::make('result')->table($exam), data: [
                'examiner_teacher_id' => $teacher->getKey(),
                'grade' => ExamGrade::VeryGood->value,
                'notes' => 'حفظ متقن',
            ])
            ->assertHasNoActionErrors()
            ->assertNotified('تم حفظ نتيجة السبر');

        $exam->refresh();
        $this->assertSame([ExamStatus::Done, ExamGrade::VeryGood], [$exam->status, $exam->grade]);
        $this->assertSame('2026-10-07', $exam->exam_date->toDateString());

        Livewire::test(ManageJuzExams::class)
            ->assertCanNotSeeTableRecords([$exam])
            ->set('activeTab', 'done')
            ->assertCanSeeTableRecords([$exam]);
    }

    public function test_an_exam_is_opened_by_hand_and_deleted(): void
    {
        $student = Student::factory()->create();

        Livewire::test(ManageJuzExams::class)
            ->callAction('open', data: ['student_id' => $student->getKey(), 'juz_no' => 1])
            ->assertHasNoActionErrors()
            ->assertNotified('تم فتح السبر');

        $exam = JuzExam::query()->sole();
        $this->assertSame([1, ExamOrigin::Manual], [$exam->juz_no, $exam->origin]);

        Livewire::test(ManageJuzExams::class)
            ->callAction('open', data: ['student_id' => $student->getKey(), 'juz_no' => 1])
            ->assertNotified('للطالب سبر منتظر لهذا الجزء.');

        Livewire::test(ManageJuzExams::class)
            ->callAction(TestAction::make('delete')->table($exam));

        $this->assertSame(0, JuzExam::query()->count());
    }

    public function test_points_are_added_and_deducted_by_hand_with_a_reason(): void
    {
        $enrollment = $this->enroll();

        Livewire::test(ManagePointTransactions::class)
            ->callAction('award', data: ['enrollment_id' => $enrollment->getKey(), 'points' => '-5', 'reason' => 'تأخر متكرر'])
            ->assertHasNoActionErrors()
            ->assertNotified('تم حفظ النقاط');

        $penalty = PointTransaction::query()->sole();
        $this->assertSame([-5, PointSource::Manual, 'تأخر متكرر'], [$penalty->points, $penalty->source, $penalty->reason]);

        Livewire::test(ManagePointTransactions::class)
            ->callAction('award', data: ['enrollment_id' => $enrollment->getKey(), 'points' => '0', 'reason' => ''])
            ->assertHasActionErrors(['points', 'reason']);

        Livewire::test(ManagePointTransactions::class)
            ->mountAction(TestAction::make('edit')->table($penalty))
            ->assertSchemaStateSet(['points' => -5, 'reason' => 'تأخر متكرر'])
            ->fillForm(['points' => '10', 'reason' => 'مسابقة'])
            ->callMountedAction()
            ->assertHasNoActionErrors();

        $this->assertSame([10, 'مسابقة'], [$penalty->refresh()->points, $penalty->reason]);

        Livewire::test(ManagePointTransactions::class)
            ->callAction(TestAction::make('delete')->table($penalty));

        $this->assertSame(0, PointTransaction::query()->count());
    }

    public function test_automatic_points_cannot_be_edited_or_deleted_from_the_points_screen(): void
    {
        $enrollment = $this->enroll();
        $this->attend($enrollment);
        $automatic = PointTransaction::query()->sole();

        Livewire::test(ManagePointTransactions::class)
            ->assertCanSeeTableRecords([$automatic])
            ->assertActionHidden(TestAction::make('edit')->table($automatic))
            ->assertActionHidden(TestAction::make('delete')->table($automatic));
    }
}
