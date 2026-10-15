<?php

namespace Tests\Feature\Panel;

use App\Filament\Resources\Students\Pages\CreateStudent;
use App\Filament\Resources\Students\Pages\EditStudent;
use App\Filament\Resources\Students\Pages\ListStudents;
use App\Filament\Resources\Students\Pages\ViewStudent;
use App\Filament\Resources\Students\RelationManagers\EnrollmentsRelationManager;
use App\Filament\Resources\Students\RelationManagers\JuzExamsRelationManager;
use App\Filament\Resources\Students\RelationManagers\PointTransactionsRelationManager;
use App\Filament\Resources\Students\RelationManagers\RecitationsRelationManager;
use App\Filament\Resources\Students\RelationManagers\TransfersRelationManager;
use App\Filament\Support\Presenters\StudentPresenter;
use App\Models\Circle;
use App\Models\CircleTransfer;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Student;
use App\Models\TajweedLevel;
use Filament\Actions\Testing\TestAction;
use Livewire\Livewire;

class StudentsPanelTest extends PanelTestCase
{
    public function test_a_student_is_registered_with_only_a_name(): void
    {
        Livewire::test(CreateStudent::class)
            ->fillForm(['full_name' => 'أحمد محمد'])
            ->call('create')
            ->assertHasNoFormErrors()
            ->assertRedirect();

        $student = Student::query()->sole();
        $this->assertSame('أحمد محمد', $student->full_name);
        $this->assertFalse($student->is_profile_complete);
    }

    public function test_a_full_profile_is_saved_and_marked_complete(): void
    {
        $level = TajweedLevel::factory()->create();

        Livewire::test(CreateStudent::class)
            ->fillForm([
                'full_name' => 'خالد العلي',
                'birth_date' => '2014-03-09',
                'phone' => '0944000111',
                'guardian_phone' => '+963 944 000 222',
                'address' => 'الميدان',
                'tajweed_level_id' => $level->getKey(),
                'prior_memorized_pages' => '40',
                'notes' => 'يحفظ جزء عمّ',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $student = Student::query()->sole();
        $this->assertSame('2014-03-09', $student->birth_date->toDateString());
        $this->assertSame(40, $student->prior_memorized_pages);
        $this->assertSame($level->getKey(), $student->tajweed_level_id);
        $this->assertTrue($student->is_profile_complete);
    }

    public function test_validation_messages_appear_under_the_fields_of_the_form(): void
    {
        Livewire::test(CreateStudent::class)
            ->fillForm([
                'full_name' => '',
                'phone' => 'abc',
                'prior_memorized_pages' => '700',
            ])
            ->call('create')
            ->assertHasFormErrors(['full_name', 'phone', 'prior_memorized_pages']);

        $this->assertSame(0, Student::query()->count());
    }

    public function test_the_edit_form_shows_the_stored_dates_and_saves_changes(): void
    {
        $student = Student::factory()->create(['full_name' => 'سامر', 'birth_date' => '2013-12-31']);

        Livewire::test(EditStudent::class, ['record' => $student->getRouteKey()])
            ->assertFormSet(['full_name' => 'سامر', 'birth_date' => '2013-12-31'])
            ->fillForm(['full_name' => 'سامر الحسن', 'phone' => '0933111222'])
            ->call('save')
            ->assertHasNoFormErrors();

        $student->refresh();
        $this->assertSame('سامر الحسن', $student->full_name);
        $this->assertSame('0933111222', $student->phone);
        $this->assertSame('2013-12-31', $student->birth_date->toDateString());
    }

    public function test_the_list_separates_active_incomplete_and_archived_students(): void
    {
        $complete = Student::factory()->complete()->create();
        $incomplete = Student::factory()->create();
        $archived = Student::factory()->archived()->create();

        Livewire::test(ListStudents::class)
            ->assertCanSeeTableRecords([$complete, $incomplete])
            ->assertCanNotSeeTableRecords([$archived])
            ->set('activeTab', 'incomplete')
            ->assertCanSeeTableRecords([$incomplete])
            ->assertCanNotSeeTableRecords([$complete, $archived])
            ->set('activeTab', 'archived')
            ->assertCanSeeTableRecords([$archived])
            ->assertCanNotSeeTableRecords([$complete, $incomplete]);
    }

    public function test_the_list_is_searched_by_name_and_shows_the_current_circle(): void
    {
        $course = Course::factory()->create(['name' => 'دورة الصيف']);
        $circle = Circle::factory()->create(['course_id' => $course->getKey(), 'name' => 'حلقة النور']);
        $enrolled = Student::factory()->create(['full_name' => 'يوسف الشامي']);
        $other = Student::factory()->create(['full_name' => 'عمر الحمصي']);
        $this->enroll($enrolled, $course, $circle);

        Livewire::test(ListStudents::class)
            ->searchTable('يوسف')
            ->assertCanSeeTableRecords([$enrolled])
            ->assertCanNotSeeTableRecords([$other])
            ->assertSee('حلقة النور (دورة الصيف)');
    }

    public function test_a_student_is_archived_and_restored_from_the_list(): void
    {
        $student = Student::factory()->create();

        Livewire::test(ListStudents::class)
            ->callAction(TestAction::make('archive')->table($student))
            ->assertNotified('تمت أرشفة الطالب');

        $this->assertTrue($student->refresh()->isArchived());

        Livewire::test(ListStudents::class)
            ->set('activeTab', 'archived')
            ->callAction(TestAction::make('restore')->table($student))
            ->assertNotified('تمت إعادة تفعيل الطالب');

        $this->assertFalse($student->refresh()->isArchived());
    }

    public function test_only_a_student_without_records_can_be_deleted(): void
    {
        $withRecords = $this->enroll()->student;
        $mistake = Student::factory()->create();

        Livewire::test(ListStudents::class)
            ->callAction(TestAction::make('delete')->table($withRecords))
            ->assertNotified('لا يمكن حذف طالب له سجلات. يمكنك أرشفته بدلاً من ذلك.');

        Livewire::test(ListStudents::class)
            ->callAction(TestAction::make('delete')->table($mistake));

        $this->assertNotNull($withRecords->fresh());
        $this->assertNull($mistake->fresh());
    }

    public function test_the_student_file_shows_what_they_did_in_each_course(): void
    {
        $student = Student::factory()->create();
        $first = $this->enroll($student, Course::factory()->create(['name' => 'الدورة الأولى', 'points_per_page' => 2]));
        $second = $this->enroll($student, Course::factory()->create(['name' => 'الدورة الثانية', 'points_per_page' => 5]));
        $this->recite($first, 1, 10);
        $this->recite($second, 11, 13);
        $this->recite($second, 582, 604);
        $this->attend($second);

        Livewire::test(ViewStudent::class, ['record' => $student->getRouteKey()])
            ->assertOk()
            ->assertSee($student->full_name);

        $relationManager = ['ownerRecord' => $student, 'pageClass' => ViewStudent::class];

        Livewire::test(EnrollmentsRelationManager::class, $relationManager)
            ->assertCanSeeTableRecords([$first, $second])
            ->assertTableColumnStateSet('recitations_sum_new_pages_count', 10, $first)
            ->assertTableColumnStateSet('point_transactions_sum_points', 20, $first)
            ->assertTableColumnStateSet('recitations_sum_new_pages_count', 26, $second)
            ->assertTableColumnStateSet('attendances_count', 1, $second);

        Livewire::test(RecitationsRelationManager::class, $relationManager)
            ->assertCountTableRecords(3)
            ->assertSee('الدورة الأولى')
            ->assertSee('الدورة الثانية');

        Livewire::test(PointTransactionsRelationManager::class, $relationManager)
            ->assertCountTableRecords(4);

        Livewire::test(JuzExamsRelationManager::class, $relationManager)
            ->assertCountTableRecords(1);
    }

    public function test_the_student_file_shows_the_attendance_rate_of_each_course(): void
    {
        $student = Student::factory()->create();
        $course = Course::factory()->create();
        $classmate = $this->enroll(course: $course);
        $enrollment = $this->enroll($student, $course);
        $elsewhere = $this->enroll($student);

        // Three sessions were held; the student attended one of them.
        $this->attend($classmate, '2026-10-01');
        $this->attend($classmate, '2026-10-03');
        $this->attend($classmate, '2026-10-05');
        $this->attend($enrollment, '2026-10-03');

        Livewire::test(EnrollmentsRelationManager::class, ['ownerRecord' => $student, 'pageClass' => ViewStudent::class])
            ->assertTableColumnStateSet('attendances_count', 1, $enrollment)
            ->assertTableColumnFormattedStateSet('attendance_rate', '33.3%', $enrollment)
            ->assertTableColumnStateSet('attendance_rate', null, $elsewhere);
    }

    public function test_the_student_file_shows_their_moves_between_circles(): void
    {
        $course = Course::factory()->create(['name' => 'دورة الصيف']);
        $from = Circle::factory()->create(['course_id' => $course->getKey(), 'name' => 'حلقة النور']);
        $to = Circle::factory()->create(['course_id' => $course->getKey(), 'name' => 'حلقة الهدى']);
        $enrollment = $this->enroll(course: $course, circle: $from);
        $transfer = $this->moveToCircle($enrollment, $to, '2026-10-05');
        $unrelated = $this->moveToCircle($this->enroll(course: $course, circle: $from), $to, '2026-10-06');

        Livewire::test(TransfersRelationManager::class, ['ownerRecord' => $enrollment->student, 'pageClass' => ViewStudent::class])
            ->assertCanSeeTableRecords([$transfer])
            ->assertCanNotSeeTableRecords([$unrelated])
            ->assertTableColumnStateSet('enrollment.course.name', 'دورة الصيف', $transfer)
            ->assertTableColumnStateSet('fromCircle.name', 'حلقة النور', $transfer)
            ->assertTableColumnStateSet('toCircle.name', 'حلقة الهدى', $transfer)
            ->assertTableColumnHidden('enrollment.student.full_name');
    }

    public function test_the_list_marks_students_waiting_for_an_exam_and_explains_incomplete_profiles(): void
    {
        $waiting = $this->enroll(Student::factory()->create(['full_name' => 'أنس']));
        $this->recite($waiting, 582, 604);
        $other = Student::factory()->complete()->create(['full_name' => 'باسل']);

        Livewire::test(ListStudents::class)
            ->assertTableColumnStateSet('pending_exams', ['الجزء 30'], $waiting->student)
            ->assertTableColumnStateSet('pending_exams', null, $other);

        $this->assertSame(
            'ينقصه: تاريخ الميلاد، رقم الطالب، رقم ولي الأمر، مكان السكن، مستوى التجويد، المحفوظ السابق',
            StudentPresenter::missingFields($waiting->student->refresh()),
        );
        $this->assertNull(StudentPresenter::missingFields($other));
    }

    public function test_a_student_is_enrolled_from_their_file_and_the_circle_must_be_chosen(): void
    {
        $student = Student::factory()->create();
        $course = Course::factory()->create();
        $circle = Circle::factory()->create(['course_id' => $course->getKey()]);
        $relationManager = ['ownerRecord' => $student, 'pageClass' => ViewStudent::class];

        Livewire::test(EnrollmentsRelationManager::class, $relationManager)
            ->callAction(TestAction::make('enroll')->table(), data: ['course_id' => $course->getKey(), 'circle_id' => null])
            ->assertHasActionErrors(['circle_id']);

        $this->assertSame(0, Enrollment::query()->count());

        Livewire::test(EnrollmentsRelationManager::class, $relationManager)
            ->callAction(TestAction::make('enroll')->table(), data: [
                'course_id' => $course->getKey(),
                'circle_id' => $circle->getKey(),
                'enrolled_on' => '2026-10-01',
            ])
            ->assertHasNoActionErrors()
            ->assertNotified('تم تسجيل الطالب في الدورة');

        $enrollment = Enrollment::query()->sole();
        $this->assertSame($student->getKey(), $enrollment->student_id);
        $this->assertSame($circle->getKey(), $enrollment->circle_id);
        $this->assertSame('2026-10-01', $enrollment->enrolled_on->toDateString());

        Livewire::test(EnrollmentsRelationManager::class, $relationManager)
            ->callAction(TestAction::make('enroll')->table(), data: [
                'course_id' => $course->getKey(),
                'circle_id' => $circle->getKey(),
            ])
            ->assertNotified('الطالب مسجَّل مسبقاً في هذه الدورة.')
            ->assertActionMounted(TestAction::make('enroll')->table());
    }

    public function test_a_transfer_needs_a_date_typed_by_the_admin(): void
    {
        $course = Course::factory()->create();
        $from = Circle::factory()->create(['course_id' => $course->getKey()]);
        $to = Circle::factory()->create(['course_id' => $course->getKey()]);
        $enrollment = $this->enroll(course: $course, circle: $from);
        $relationManager = ['ownerRecord' => $enrollment->student, 'pageClass' => ViewStudent::class];

        Livewire::test(EnrollmentsRelationManager::class, $relationManager)
            ->mountAction(TestAction::make('transfer')->table($enrollment))
            ->assertSchemaStateSet(['transferred_on' => null])
            ->fillForm(['to_circle_id' => $to->getKey()])
            ->callMountedAction()
            ->assertHasActionErrors(['transferred_on']);

        $this->assertSame($from->getKey(), $enrollment->fresh()->circle_id);

        Livewire::test(EnrollmentsRelationManager::class, $relationManager)
            ->callAction(TestAction::make('transfer')->table($enrollment), data: [
                'to_circle_id' => $to->getKey(),
                'transferred_on' => '2026-10-05',
                'notes' => 'بطلب ولي الأمر',
            ])
            ->assertHasNoActionErrors()
            ->assertNotified('تم نقل الطالب');

        $this->assertSame($to->getKey(), $enrollment->fresh()->circle_id);
        $transfer = CircleTransfer::query()->sole();
        $this->assertSame('2026-10-05', $transfer->transferred_on->toDateString());
        $this->assertSame('بطلب ولي الأمر', $transfer->notes);
    }

    public function test_enrollment_dates_are_corrected_and_an_unused_enrollment_is_deleted(): void
    {
        $enrollment = $this->enroll();
        $relationManager = ['ownerRecord' => $enrollment->student, 'pageClass' => ViewStudent::class];

        Livewire::test(EnrollmentsRelationManager::class, $relationManager)
            ->mountAction(TestAction::make('dates')->table($enrollment))
            ->assertSchemaStateSet(['enrolled_on' => $enrollment->enrolled_on->toDateString(), 'left_on' => null])
            ->fillForm(['left_on' => '2026-10-06'])
            ->callMountedAction()
            ->assertHasNoActionErrors()
            ->assertNotified('تم حفظ التواريخ');

        $this->assertSame('2026-10-06', $enrollment->fresh()->left_on->toDateString());

        Livewire::test(EnrollmentsRelationManager::class, $relationManager)
            ->callAction(TestAction::make('delete')->table($enrollment));

        $this->assertNull($enrollment->fresh());
    }
}
