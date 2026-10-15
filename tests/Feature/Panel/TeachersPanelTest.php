<?php

namespace Tests\Feature\Panel;

use App\Actions\Teachers\SaveTeacher;
use App\Filament\Pages\ScanAttendance;
use App\Filament\Resources\Courses\Pages\EditCourse;
use App\Filament\Resources\Courses\RelationManagers\CirclesRelationManager;
use App\Filament\Resources\TeacherAttendances\Pages\ManageTeacherAttendances;
use App\Filament\Resources\Teachers\Pages\ManageTeachers;
use App\Filament\Support\Cards\CardPrintActions;
use App\Filament\Support\Presenters\TeacherPresenter;
use App\Models\Circle;
use App\Models\Course;
use App\Models\Teacher;
use App\Models\TeacherAttendance;
use App\Services\Circles\CircleLogos;
use App\Services\Teachers\TeacherCards;
use App\Services\Teachers\TeacherPhotos;
use Filament\Actions\Testing\TestAction;
use Illuminate\Support\Js;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Support\Pictures;

/**
 * The screens of the teachers: their photo and their cards (one per
 * course), the circle's logo, the teachers' attendance register and their
 * card at the scan desk.
 */
class TeachersPanelTest extends PanelTestCase
{
    private function teacher(string $name = 'الشيخ ياسر', bool $withPhoto = false): Teacher
    {
        return app(SaveTeacher::class)->handle(['full_name' => $name] + ($withPhoto ? ['photo' => Pictures::png()] : []));
    }

    public function test_a_teacher_is_added_with_a_photo(): void
    {
        Livewire::test(ManageTeachers::class)
            ->callAction('create', data: ['full_name' => 'الشيخ ياسر', 'photo' => Pictures::png()])
            ->assertHasNoActionErrors();

        $teacher = Teacher::query()->sole();
        Storage::disk(TeacherPhotos::DISK)->assertExists((string) $teacher->photo_path);

        Livewire::test(ManageTeachers::class)
            ->assertCanSeeTableRecords([$teacher])
            ->callAction(TestAction::make('edit')->table($teacher), data: ['remove_photo' => true])
            ->assertHasNoActionErrors();

        $this->assertNull($teacher->fresh()->photo_path);
    }

    public function test_the_teachers_cards_are_opened_for_a_course(): void
    {
        $autumn = Course::factory()->create(['name' => 'دورة الخريف']);
        $teacher = $this->teacher();
        $inTwo = $this->teacher('الأستاذ بلال');
        $noCircle = $this->teacher('الأستاذ خالد');
        $spring = Course::factory()->create(['name' => 'دورة الربيع', 'start_date' => now()->subDays(10)->toDateString()]);
        Circle::factory()->create(['course_id' => $autumn->getKey(), 'supervisor_teacher_id' => $teacher->getKey(), 'assistant_teacher_id' => $inTwo->getKey()]);
        Circle::factory()->create(['course_id' => $spring->getKey(), 'supervisor_teacher_id' => $inTwo->getKey()]);

        $opens = fn (string $url): string => 'window.open('.Js::from($url).", '_blank')";

        Livewire::test(ManageTeachers::class)
            // A teacher of one running course: their card of it opens at once.
            ->assertActionHasUrl(TestAction::make('card')->table($teacher), CardPrintActions::teachersUrl($autumn->getKey(), [$teacher->getKey()]))
            // A teacher of two: the admin picks the course (the newest is chosen already).
            ->mountAction(TestAction::make('card')->table($inTwo))
            ->assertSchemaStateSet(['course_id' => $spring->getKey()])
            ->callMountedAction()
            ->assertJs($opens(CardPrintActions::teachersUrl($spring->getKey(), [$inTwo->getKey()])))
            // Without a running circle there is no card to print.
            ->assertActionHidden(TestAction::make('card')->table($noCircle));

        // The whole course from the top of the page; the course has to be chosen.
        Livewire::test(ManageTeachers::class)
            ->callAction('cards', data: ['course_id' => $autumn->getKey()])
            ->assertJs($opens(CardPrintActions::teachersUrl($autumn->getKey())));

        Livewire::test(ManageTeachers::class)
            ->callAction('cards', data: ['course_id' => null])
            ->assertHasActionErrors(['course_id']);

        // The ticked teachers who teach in the chosen course.
        Livewire::test(ManageTeachers::class)
            ->selectTableRecords([$teacher, $noCircle])
            ->callAction(TestAction::make('printCards')->table()->bulk(), data: ['course_id' => $autumn->getKey()])
            ->assertJs($opens(CardPrintActions::teachersUrl($autumn->getKey(), [$teacher->getKey()])));

        Livewire::test(ManageTeachers::class)
            ->selectTableRecords([$noCircle])
            ->callAction(TestAction::make('printCards')->table()->bulk(), data: ['course_id' => $autumn->getKey()])
            ->assertNotified('لا أحد من الأساتذة المحدَّدين يشرف على حلقة من هذه الدورة أو يساعد فيها.')
            ->assertNoJs();
    }

    public function test_a_circle_gets_its_logo_from_the_circles_of_the_course(): void
    {
        $course = Course::factory()->create();
        $on = ['ownerRecord' => $course, 'pageClass' => EditCourse::class];

        Livewire::test(CirclesRelationManager::class, $on)
            ->callAction(TestAction::make('create')->table(), data: ['name' => 'تاج الوقار', 'logo' => Pictures::png('logo.png')])
            ->assertHasNoActionErrors();

        $circle = Circle::query()->sole();
        Storage::disk(CircleLogos::DISK)->assertExists((string) $circle->logo_path);

        Livewire::test(CirclesRelationManager::class, $on)
            ->callAction(TestAction::make('edit')->table($circle), data: ['logo' => Pictures::pretender('logo.png')])
            ->assertHasActionErrors(['logo']);

        Livewire::test(CirclesRelationManager::class, $on)
            ->callAction(TestAction::make('edit')->table($circle), data: ['remove_logo' => true])
            ->assertHasNoActionErrors();

        $this->assertNull($circle->fresh()->logo_path);
    }

    public function test_a_courses_teacher_cards_show_the_photo_the_name_the_course_and_the_code_but_no_circle(): void
    {
        $course = Course::factory()->create(['name' => 'دورة الخريف 2026']);
        $supervisor = $this->teacher('الشيخ ياسر', withPhoto: true);
        $assistant = $this->teacher('الأستاذ بلال');
        $elsewhere = $this->teacher('الأستاذ خالد');
        Circle::factory()->create([
            'course_id' => $course->getKey(),
            'name' => 'حلقة النور',
            'supervisor_teacher_id' => $supervisor->getKey(),
            'assistant_teacher_id' => $assistant->getKey(),
        ]);
        Circle::factory()->create(['supervisor_teacher_id' => $elsewhere->getKey()]);

        $page = $this->get(CardPrintActions::teachersUrl($course->getKey()))
            ->assertOk()
            ->assertSee('بطاقات أساتذة دورة الخريف 2026')
            ->assertDontSee('الأستاذ خالد')
            // The teacher's design: without the green band of the circle.
            ->assertSee(asset('img/card/background-teacher.png'), escape: false)
            ->assertDontSee(asset('img/card/background.png'), escape: false)
            ->getContent();

        $cards = app(TeacherCards::class);
        $this->assertStringContainsString('data-code="'.$cards->of($supervisor, $course)->card_code.'"', $page);
        $this->assertStringContainsString('data-code="'.$cards->of($assistant, $course)->card_code.'"', $page);
        $this->assertStringContainsString('aria-label="الشيخ ياسر — دورة الخريف 2026"', $page);
        // The course is written on the card, its number in 0-9.
        $this->assertSame(2, substr_count($page, 'class="rawda-badge__course"'));
        $this->assertStringContainsString('دورة الخريف <tspan class="rawda-badge__digits">2026</tspan></text>', $page);
        $this->assertStringContainsString((string) TeacherPresenter::photoUrl($supervisor), $page);
        $this->assertStringNotContainsString('حلقة النور', $page);
        $this->assertSame(0, substr_count($page, 'class="rawda-badge__circle"'));
        $this->assertSame(0, substr_count($page, 'class="rawda-badge__logo"'));

        // A course has to be named, and a chosen teacher is a number.
        $this->get(route('filament.admin.teacher-cards.print'))->assertSessionHasErrors(['course_id']);
        $this->get(CardPrintActions::teachersUrl($course->getKey()).'&teacher_ids[]=abc')->assertSessionHasErrors(['teacher_ids.0']);
    }

    public function test_the_teachers_card_is_taken_at_the_scan_desk(): void
    {
        $teacher = $this->teacher('الشيخ ياسر', withPhoto: true);
        $course = Course::factory()->create(['name' => 'دورة الخريف']);
        Circle::factory()->create(['course_id' => $course->getKey(), 'name' => 'حلقة النور', 'supervisor_teacher_id' => $teacher->getKey()]);
        $code = app(TeacherCards::class)->of($teacher, $course)->card_code;

        Livewire::test(ScanAttendance::class)
            ->set('cardCode', $code)
            ->call('scan')
            ->assertSet('result.tone', 'success')
            ->assertSet('result.teacher', true)
            ->assertSet('result.name', 'الشيخ ياسر')
            ->assertSet('result.circle', 'مشرف حلقة النور')
            ->assertSet('result.course', 'دورة الخريف')
            ->assertSet('result.photo', TeacherPresenter::photoUrl($teacher))
            ->assertSeeHtml('<p class="rawda-scan__kind">أستاذ</p>')
            ->set('cardCode', $code)
            ->call('scan')
            ->assertSet('result.tone', 'warning');

        $this->assertSame(1, TeacherAttendance::query()->count());
    }

    public function test_a_teachers_attendance_is_added_by_hand_and_removed_from_the_register(): void
    {
        $teacher = $this->teacher();
        $course = Course::factory()->create(['name' => 'دورة الخريف']);
        Circle::factory()->create(['course_id' => $course->getKey(), 'name' => 'حلقة النور', 'supervisor_teacher_id' => $teacher->getKey()]);

        Livewire::test(ManageTeacherAttendances::class)
            ->callAction('manual', data: ['teacher_id' => $teacher->getKey(), 'course_id' => $course->getKey(), 'attended_on' => '2026-10-06'])
            ->assertHasNoActionErrors();

        $attendance = TeacherAttendance::query()->sole();
        $this->assertSame('2026-10-06', $attendance->session->session_date->toDateString());

        // The same day twice is refused with a reason.
        Livewire::test(ManageTeacherAttendances::class)
            ->callAction('manual', data: ['teacher_id' => $teacher->getKey(), 'course_id' => $course->getKey(), 'attended_on' => '2026-10-06'])
            ->assertNotified('حضور الأستاذ مسجَّل مسبقاً في هذا التاريخ.');

        Livewire::test(ManageTeacherAttendances::class)
            ->assertCanSeeTableRecords([$attendance])
            ->assertSee('مشرف حلقة النور')
            ->callAction(TestAction::make('delete')->table($attendance));

        $this->assertSame(0, TeacherAttendance::query()->count());
    }
}
