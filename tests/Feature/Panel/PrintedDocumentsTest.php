<?php

namespace Tests\Feature\Panel;

use App\Actions\Documents\SheetOptions;
use App\Actions\Enrollments\UpdateEnrollmentDates;
use App\Actions\Students\ArchiveStudent;
use App\Enums\CourseStatus;
use App\Enums\ExamGrade;
use App\Filament\Pages\Reports\CircleSheets;
use App\Filament\Resources\Courses\Pages\EditCourse;
use App\Filament\Resources\Courses\RelationManagers\CirclesRelationManager;
use App\Filament\Resources\Students\Pages\ListStudents;
use App\Filament\Resources\Students\Pages\ViewStudent;
use App\Filament\Resources\Students\StudentResource;
use App\Filament\Support\Documents\DocumentActions;
use App\Models\Circle;
use App\Models\Course;
use App\Models\Student;
use App\Support\Brand;
use Filament\Actions\Testing\TestAction;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Js;
use Livewire\Livewire;
use Tests\Concerns\SeedsReportWeek;

/**
 * The documents printed on A4 under the mosque's logo: a student's file,
 * the list of a circle's students, and their attendance codes; and the
 * buttons that open them. On the week of SeedsReportWeek.
 */
class PrintedDocumentsTest extends PanelTestCase
{
    use SeedsReportWeek;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedReportWeek();
    }

    private function opens(string $url): string
    {
        return 'window.open('.Js::from($url).", '_blank')";
    }

    public function test_a_students_file_has_their_details_their_pages_and_every_session(): void
    {
        $bilal = $this->bilal->student;
        $bilal->update(['phone' => '0933111222', 'guardian_phone' => '0944333444', 'address' => 'المزة']);
        // An older course of his, with nothing in it yet.
        $spring = Course::factory()->create(['name' => 'دورة الربيع', 'start_date' => '2026-03-01']);
        $this->enroll($bilal, $spring, enrolledOn: '2026-03-01');

        $page = $this->get(DocumentActions::studentFileUrl($bilal->getKey()))
            ->assertOk()
            ->assertSee('<img src="'.Brand::logoUrl().'"', escape: false)
            ->assertSee('<h1 class="letterhead__title">بلال</h1>', escape: false)
            ->assertSee('ملف الطالب')
            ->assertSee('0933111222')
            ->assertSee('0944333444')
            ->assertSee('المزة')
            // The newest course first.
            ->assertSeeInOrder(['دورة الخريف — حلقة أ', 'دورة الربيع'])
            // His recitation: the day, the pages and the teacher.
            ->assertSeeInOrder(['05/10/2026', '1 – 4', 'الشيخ عمر'])
            ->assertSee('لم يسمّع الطالب شيئاً في هذه الدورة.')
            ->assertSee('لم تُعقد جلسات في هذه الدورة بعد.')
            ->assertSee('الجلسات: 3 · حضر: 1 · غاب: 2')
            ->assertSee('دورتان')
            ->getContent();

        // Three sessions in the autumn: there on the first, absent from the two others.
        $this->assertSame(1, substr_count($page, 'status status--present'));
        $this->assertSame(2, substr_count($page, 'status status--absent'));
        $this->assertStringContainsString('يدوي', $page);
        $this->assertStringNotContainsString('تشرين', $page);
    }

    public function test_a_students_file_can_hold_one_of_their_courses_only(): void
    {
        $bilal = $this->bilal->student;
        $spring = Course::factory()->create(['name' => 'دورة الربيع', 'start_date' => '2026-03-01']);
        $this->enroll($bilal, $spring, enrolledOn: '2026-03-01');

        $this->get(DocumentActions::studentFileUrl($bilal->getKey(), $spring->getKey()))
            ->assertOk()
            ->assertSee('دورة الربيع')
            ->assertDontSee('دورة الخريف');

        // A course the student is not in is refused.
        $other = Course::factory()->create();
        $this->get(DocumentActions::studentFileUrl($bilal->getKey(), $other->getKey()))->assertSessionHasErrors(['course_id']);

        Auth::logout();
        $this->get(DocumentActions::studentFileUrl($bilal->getKey()))->assertRedirect('/admin/login');
    }

    public function test_the_students_file_is_opened_from_their_page_and_their_row(): void
    {
        $ahmad = $this->ahmad->student;
        $bilal = $this->bilal->student;
        $spring = Course::factory()->create(['name' => 'دورة الربيع', 'start_date' => '2026-03-01']);
        $this->enroll($bilal, $spring, enrolledOn: '2026-03-01');

        // A student of one course: the file opens at once.
        Livewire::test(ViewStudent::class, ['record' => $ahmad->getRouteKey()])
            ->assertActionHasUrl('studentFile', DocumentActions::studentFileUrl($ahmad->getKey()));

        // A student of two: one course, or all of them.
        Livewire::test(ViewStudent::class, ['record' => $bilal->getRouteKey()])
            ->callAction('studentFile', data: ['course_id' => $spring->getKey()])
            ->assertJs($this->opens(DocumentActions::studentFileUrl($bilal->getKey(), $spring->getKey())));

        Livewire::test(ViewStudent::class, ['record' => $bilal->getRouteKey()])
            ->callAction('studentFile', data: ['course_id' => null])
            ->assertJs($this->opens(DocumentActions::studentFileUrl($bilal->getKey())));

        Livewire::test(ListStudents::class)
            ->assertActionHasUrl(TestAction::make('studentFile')->table($ahmad), DocumentActions::studentFileUrl($ahmad->getKey()));

        // The back button leads to the page the admin came from.
        $from = StudentResource::getUrl('view', ['record' => $ahmad]);
        $this->get(DocumentActions::studentFileUrl($ahmad->getKey()), ['referer' => $from])
            ->assertSee('<a href="'.e($from).'" class="toolbar-back">', escape: false);
    }

    public function test_a_circles_sheet_lists_its_students_with_their_phones_and_their_records_in_the_course(): void
    {
        $this->ahmad->student->update(['phone' => '0933000001', 'guardian_phone' => '0944000001']);
        $this->circleA->update(['supervisor_teacher_id' => $this->yaser->getKey()]);
        // Khalid moves to the circle with what he did; a student who left is not listed.
        $this->moveToCircle($this->khalid, $this->circleA, '2026-10-06');
        $gone = $this->enroll(Student::factory()->create(['full_name' => 'سامر الراحل']), $this->course, $this->circleA);
        app(UpdateEnrollmentDates::class)->handle($gone, ['enrolled_on' => $gone->enrolled_on->toDateString(), 'left_on' => '2026-10-04']);

        $page = $this->get(DocumentActions::circleStudentsUrl($this->circleA->getKey()))
            ->assertOk()
            // The logo at the top right, the circle and the course next to it.
            ->assertSeeInOrder([
                '<img src="'.Brand::logoUrl().'"',
                '<h1 class="letterhead__title">حلقة أ</h1>',
                '<p class="letterhead__line">دورة الخريف</p>',
                'المشرف: الشيخ ياسر',
            ], escape: false)
            ->assertSeeInOrder(['اسم الطالب', 'رقم الطالب', 'رقم ولي الأمر', 'الصفحات المحفوظة في الدورة', 'السبر في الدورة', 'الحضور في الدورة'])
            // Which pages, not only how many: أحمد recited 1-10 then 5-12.
            ->assertSeeInOrder(['أحمد', '0933000001', '0944000001', '12 صفحة', '1–12', '3 من 3', 'بلال', '4 صفحات', '1–4', '1 من 3', 'خالد', '0 صفحة', '2 من 3'])
            ->assertSee('عدد الطلاب: 3')
            ->assertDontSee('سامر الراحل')
            ->assertDontSee('سجلّ كل طالب في كل الدورات')
            ->getContent();

        $this->assertSame(1, substr_count($page, '<article class="document">'));
        $this->assertStringContainsString('16 صفحة', $page);
    }

    public function test_the_sheet_shows_the_last_exam_of_each_juz(): void
    {
        $this->examine($this->ahmad, 30, '2026-10-02', ExamGrade::Excellent);
        $this->examine($this->ahmad, 29, '2026-10-03', ExamGrade::Retake);
        // The retake is waiting.
        $this->examine($this->ahmad, 29);
        $this->examine($this->bilal, 28, '2026-10-05', ExamGrade::VeryGood);

        $this->get(DocumentActions::circleStudentsUrl($this->circleA->getKey()))
            ->assertOk()
            ->assertSeeInOrder(['أحمد', '29 (بانتظار السبر)، 30 (ممتاز)', 'بلال', '28 (جيد جداً)'])
            ->assertSee('أجزاء نجحوا فيها: 2')
            ->assertSee('السبر: رقم الجزء وتقدير آخر سبر فيه.');
    }

    public function test_the_admin_chooses_the_columns_of_the_sheet(): void
    {
        $this->bilal->student->update(['guardian_phone' => '0944333444']);

        $this->get(DocumentActions::circleStudentsUrl($this->circleA->getKey(), options: new SheetOptions(phones: false, exams: false)))
            ->assertOk()
            ->assertSeeInOrder(['اسم الطالب', 'الصفحات المحفوظة في الدورة', 'الحضور في الدورة'])
            ->assertDontSee('رقم ولي الأمر')
            ->assertDontSee('0944333444')
            ->assertDontSee('السبر في الدورة');

        // Nothing ticked: the names alone.
        $this->get(DocumentActions::circleStudentsUrl($this->circleA->getKey(), options: new SheetOptions(phones: false, pages: false, exams: false, attendance: false)))
            ->assertOk()
            ->assertSeeInOrder(['أحمد', 'بلال'])
            ->assertDontSee('الصفحات المحفوظة')
            ->assertDontSee('الحضور في الدورة')
            ->assertSee('<td colspan="2">عدد الطلاب: 2</td>', escape: false);

        $this->get(route('filament.admin.print.circle-students', ['circle_id' => $this->circleA->getKey(), 'history' => 'ever', 'pages' => 'maybe']))
            ->assertSessionHasErrors(['history', 'pages']);
    }

    public function test_with_every_course_the_sheet_gives_each_students_whole_record(): void
    {
        $bilal = $this->bilal->student;
        $bilal->update(['prior_memorized_pages' => 40]);
        // His older course: ten more pages and a juz exam.
        $spring = Course::factory()->create(['name' => 'دورة الربيع', 'start_date' => '2026-03-01']);
        $before = $this->enroll($bilal, $spring, enrolledOn: '2026-03-01');
        $this->recite($before, 21, 30, '2026-03-10');
        $this->examine($before, 30, '2026-03-20', ExamGrade::Excellent);

        // Only this course: what he did in it.
        $this->get(DocumentActions::circleStudentsUrl($this->circleA->getKey()))
            ->assertSeeInOrder(['بلال', '4 صفحات', '1–4'])
            ->assertDontSee('21–30');

        $this->get(DocumentActions::circleStudentsUrl($this->circleA->getKey(), options: new SheetOptions(allCourses: true)))
            ->assertOk()
            ->assertSee('سجلّ كل طالب في كل الدورات منذ دخوله النظام')
            ->assertSeeInOrder(['الصفحات المحفوظة في كل الدورات', 'السبر في كل الدورات', 'الحضور في كل الدورات'])
            ->assertSeeInOrder(['بلال', '14 صفحة', '1–4، 21–30', 'وقبل دخول النظام: 40 صفحة', '30 (ممتاز)', '1 من 3']);
    }

    public function test_every_circle_of_a_course_is_printed_on_its_own_pages(): void
    {
        Circle::factory()->create(['course_id' => $this->course->getKey(), 'name' => 'حلقة بلا طلاب']);

        $page = $this->get(DocumentActions::circleStudentsUrl(courseId: $this->course->getKey()))
            ->assertOk()
            ->assertSeeInOrder(['حلقة أ', 'أحمد', 'بلال', 'حلقة ب', 'خالد'])
            ->assertDontSee('حلقة بلا طلاب')
            ->getContent();

        $this->assertSame(2, substr_count($page, '<article class="document">'));

        // A circle chosen on its own is printed even when it is empty.
        $empty = Circle::query()->where('name', 'حلقة بلا طلاب')->sole();
        $this->get(DocumentActions::circleStudentsUrl($empty->getKey()))
            ->assertOk()
            ->assertSee('لا يوجد طلاب في هذه الحلقة الآن.');

        // A circle or a course, never neither nor both.
        $this->get(route('filament.admin.print.circle-students'))->assertSessionHasErrors(['course_id']);
        $this->get(route('filament.admin.print.circle-students', ['course_id' => $this->course->getKey(), 'circle_id' => $this->circleA->getKey()]))
            ->assertSessionHasErrors(['course_id']);
        $this->get(route('filament.admin.print.circle-codes', ['circle_id' => 'abc']))->assertSessionHasErrors(['circle_id']);
    }

    public function test_the_attendance_codes_of_a_circle_are_fifteen_to_a_page(): void
    {
        foreach (range(1, 14) as $number) {
            $this->enroll(Student::factory()->create(['full_name' => "طالب {$number}"]), $this->course, $this->circleA);
        }

        $page = $this->get(DocumentActions::circleCodesUrl($this->circleA->getKey()))
            ->assertOk()
            ->assertSee('رموز الحضور')
            ->assertSee('<h1 class="letterhead__title">حلقة أ</h1>', escape: false)
            ->getContent();

        $this->assertSame(2, substr_count($page, '<article class="document codes-page">'));
        $this->assertSame(16, substr_count($page, 'class="code__qr"'));
        $this->assertStringContainsString('data-code="'.$this->ahmad->card_code.'"', $page);
        $this->assertStringContainsString('data-code="'.$this->bilal->card_code.'"', $page);
        $this->assertStringNotContainsString('data-code="'.$this->khalid->card_code.'"', $page);

        // Each code is the drawing of the card's QR.
        preg_match('#src="data:image/svg\+xml;base64,([^"]+)"#', $page, $drawing);
        $this->assertStringStartsWith('<svg', base64_decode($drawing[1]));
    }

    public function test_the_sheets_are_opened_from_the_reports_and_from_the_course(): void
    {
        Livewire::test(CircleSheets::class)
            ->assertCanSeeTableRecords([$this->circleA, $this->circleB])
            ->assertActionHasUrl(TestAction::make('circleCodes')->table($this->circleB), DocumentActions::circleCodesUrl($this->circleB->getKey()))
            // The list asks whose records and which columns; it opens on the course and everything.
            ->mountAction(TestAction::make('circleStudents')->table($this->circleA))
            ->assertActionDataSet(['history' => SheetOptions::THIS_COURSE, 'columns' => SheetOptions::COLUMNS])
            ->callMountedAction()
            ->assertJs($this->opens(DocumentActions::circleStudentsUrl($this->circleA->getKey(), options: new SheetOptions)));

        Livewire::test(CircleSheets::class)
            ->callAction(TestAction::make('circleStudents')->table($this->circleA), data: [
                'history' => SheetOptions::ALL_COURSES,
                'columns' => ['pages', 'exams'],
            ])
            ->assertJs($this->opens(DocumentActions::circleStudentsUrl($this->circleA->getKey(), options: new SheetOptions(allCourses: true, phones: false, attendance: false))));

        Livewire::test(CircleSheets::class)
            ->callAction('courseStudents', data: ['course_id' => $this->course->getKey(), 'history' => SheetOptions::THIS_COURSE, 'columns' => ['attendance']])
            ->assertJs($this->opens(DocumentActions::circleStudentsUrl(courseId: $this->course->getKey(), options: new SheetOptions(phones: false, pages: false, exams: false))));

        Livewire::test(CircleSheets::class)
            ->callAction('courseCodes', data: ['course_id' => null])
            ->assertHasActionErrors(['course_id']);

        Livewire::test(CirclesRelationManager::class, ['ownerRecord' => $this->course, 'pageClass' => EditCourse::class])
            ->callAction(TestAction::make('circleStudents')->table($this->circleA), data: ['history' => SheetOptions::THIS_COURSE, 'columns' => SheetOptions::COLUMNS])
            ->assertJs($this->opens(DocumentActions::circleStudentsUrl($this->circleA->getKey(), options: new SheetOptions)))
            ->assertActionHasUrl(TestAction::make('circleCodes')->table($this->circleA), DocumentActions::circleCodesUrl($this->circleA->getKey()));
    }

    public function test_the_mosques_sheet_lists_every_student_in_the_system_by_name_with_their_circle_now(): void
    {
        $this->ahmad->student->update(['phone' => '0933000001']);
        // In no course yet; only in a finished course; archived.
        Student::factory()->create(['full_name' => 'ياسين']);
        $said = $this->enroll(Student::factory()->create(['full_name' => 'سعيد']), Course::factory()->create(['name' => 'دورة الربيع', 'start_date' => '2026-03-01']), enrolledOn: '2026-03-01');
        $this->recite($said, 100, 104, '2026-03-10');
        $said->course->update(['status' => CourseStatus::Finished]);
        app(ArchiveStudent::class)->handle(Student::factory()->create(['full_name' => 'زيد']));

        $page = $this->get(DocumentActions::mosqueStudentsUrl(new SheetOptions(allCourses: true)))
            ->assertOk()
            ->assertSeeInOrder([
                '<h1 class="letterhead__title">طلاب '.e(app(Brand::class)->name()).'</h1>',
                '<p class="letterhead__line">كل الطلاب المسجّلين في النظام</p>',
                'سجلّ كل طالب في كل الدورات منذ دخوله النظام',
            ], escape: false)
            ->assertSeeInOrder(['اسم الطالب', 'الحلقة الآن', 'رقم الطالب', 'رقم ولي الأمر', 'الصفحات المحفوظة في كل الدورات', 'السبر في كل الدورات', 'الحضور في كل الدورات'])
            // By name, each with the circle they are in now (none for the finished course or none at all).
            ->assertSeeInOrder(['أحمد', 'حلقة أ', '0933000001', '12 صفحة', '1–12', '3 من 3', 'بلال', 'حلقة أ', 'خالد', 'حلقة ب', 'سعيد', '—', '5 صفحات', '100–104', 'ياسين', '—', '0 صفحة', 'لا جلسات'])
            ->assertSee('<td colspan="5">عدد الطلاب: 5</td>', escape: false)
            ->assertDontSee('زيد')
            ->getContent();

        $this->assertSame(1, substr_count($page, '<article class="document">'));

        // Archived students too, when asked: marked as such.
        $this->get(DocumentActions::mosqueStudentsUrl(new SheetOptions(allCourses: true, phones: false), withArchived: true))
            ->assertOk()
            ->assertSee('كل الطلاب المسجّلين في النظام، والمؤرشفون معهم')
            ->assertSeeInOrder(['خالد', 'زيد', 'ملفه مؤرشف', 'سعيد'])
            ->assertSee('<td colspan="3">عدد الطلاب: 6</td>', escape: false);
    }

    public function test_the_mosques_sheet_of_one_course_lists_its_students_from_all_its_circles(): void
    {
        Student::factory()->create(['full_name' => 'ياسين']);
        $gone = $this->enroll(Student::factory()->create(['full_name' => 'سامر الراحل']), $this->course, $this->circleB);
        app(UpdateEnrollmentDates::class)->handle($gone, ['enrolled_on' => $gone->enrolled_on->toDateString(), 'left_on' => '2026-10-04']);

        $this->get(DocumentActions::mosqueStudentsUrl(new SheetOptions(phones: false, exams: false), $this->course->getKey()))
            ->assertOk()
            ->assertSee('<p class="letterhead__line">دورة الخريف</p>', escape: false)
            ->assertSee('طلاب الدورة من كل حلقاتها، وما فعلوه فيها')
            ->assertSeeInOrder(['اسم الطالب', 'الحلقة', 'الصفحات المحفوظة في الدورة', 'الحضور في الدورة'])
            ->assertSeeInOrder(['أحمد', 'حلقة أ', '12 صفحة', 'بلال', 'حلقة أ', '4 صفحات', 'خالد', 'حلقة ب', '2 من 3'])
            ->assertSee('عدد الطلاب: 3')
            ->assertDontSee('ياسين')
            ->assertDontSee('سامر الراحل')
            ->assertDontSee('الحلقة الآن');

        // One course needs the course; whose records must be said.
        $this->get(route('filament.admin.print.mosque-students', ['history' => SheetOptions::THIS_COURSE]))->assertSessionHasErrors(['course_id']);
        $this->get(route('filament.admin.print.mosque-students', ['history' => 'ever']))->assertSessionHasErrors(['history']);
        $this->get(route('filament.admin.print.mosque-students'))->assertSessionHasErrors(['history']);
    }

    public function test_the_mosques_sheet_is_opened_from_the_circle_sheets_page(): void
    {
        Livewire::test(CircleSheets::class)
            ->mountAction('mosqueStudents')
            ->assertActionDataSet(['history' => SheetOptions::ALL_COURSES, 'archived' => false, 'columns' => SheetOptions::COLUMNS])
            ->callMountedAction()
            ->assertJs($this->opens(DocumentActions::mosqueStudentsUrl(new SheetOptions(allCourses: true))));

        Livewire::test(CircleSheets::class)
            ->callAction('mosqueStudents', data: ['history' => SheetOptions::ALL_COURSES, 'archived' => true, 'columns' => ['pages']])
            ->assertJs($this->opens(DocumentActions::mosqueStudentsUrl(new SheetOptions(allCourses: true, phones: false, exams: false, attendance: false), withArchived: true)));

        Livewire::test(CircleSheets::class)
            ->callAction('mosqueStudents', data: ['history' => SheetOptions::THIS_COURSE, 'course_id' => $this->course->getKey(), 'columns' => SheetOptions::COLUMNS])
            ->assertJs($this->opens(DocumentActions::mosqueStudentsUrl(new SheetOptions, $this->course->getKey())));

        Livewire::test(CircleSheets::class)
            ->callAction('mosqueStudents', data: ['history' => SheetOptions::THIS_COURSE, 'course_id' => null])
            ->assertHasActionErrors(['course_id']);
    }
}

