<?php

namespace Tests\Feature\Panel;

use App\Actions\Enrollments\UpdateEnrollmentDates;
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

    public function test_a_circles_sheet_lists_its_students_with_their_phones_and_their_pages_in_the_course(): void
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
            ->assertSeeInOrder(['اسم الطالب', 'رقم الطالب', 'رقم ولي الأمر', 'الصفحات المحفوظة في الدورة'])
            ->assertSeeInOrder(['أحمد', '0933000001', '0944000001', '<td class="is-center">12</td>', 'بلال', '<td class="is-center">4</td>', 'خالد', '<td class="is-center">0</td>'], escape: false)
            ->assertSee('عدد الطلاب: 3')
            ->assertDontSee('سامر الراحل')
            ->getContent();

        $this->assertSame(1, substr_count($page, '<article class="document">'));
        $this->assertStringContainsString('<td class="is-center">16</td>', $page);
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
            ->assertActionHasUrl(TestAction::make('circleStudents')->table($this->circleA), DocumentActions::circleStudentsUrl($this->circleA->getKey()))
            ->assertActionHasUrl(TestAction::make('circleCodes')->table($this->circleB), DocumentActions::circleCodesUrl($this->circleB->getKey()))
            ->callAction('courseStudents', data: ['course_id' => $this->course->getKey()])
            ->assertJs($this->opens(DocumentActions::circleStudentsUrl(courseId: $this->course->getKey())));

        Livewire::test(CircleSheets::class)
            ->callAction('courseCodes', data: ['course_id' => null])
            ->assertHasActionErrors(['course_id']);

        Livewire::test(CirclesRelationManager::class, ['ownerRecord' => $this->course, 'pageClass' => EditCourse::class])
            ->assertActionHasUrl(TestAction::make('circleStudents')->table($this->circleA), DocumentActions::circleStudentsUrl($this->circleA->getKey()))
            ->assertActionHasUrl(TestAction::make('circleCodes')->table($this->circleA), DocumentActions::circleCodesUrl($this->circleA->getKey()));
    }
}
