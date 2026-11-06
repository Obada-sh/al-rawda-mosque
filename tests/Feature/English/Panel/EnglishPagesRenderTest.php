<?php

namespace Tests\Feature\English\Panel;

use App\Filament\English\Pages\Dashboard;
use App\Filament\English\Resources\EnglishCourses\EnglishCourseResource;
use App\Filament\English\Resources\EnglishLevels\EnglishLevelResource;
use App\Filament\English\Resources\EnglishStudents\EnglishStudentResource;
use App\Filament\English\Resources\EnglishStudents\Pages\ViewEnglishStudent;
use App\Filament\English\Resources\EnglishStudents\RelationManagers\EnglishCoursesRelationManager;
use App\Filament\English\Resources\EnglishTeachers\EnglishTeacherResource;
use App\Filament\Pages\Dashboard as MosqueDashboard;
use App\Filament\Resources\Students\StudentResource;
use App\Models\English\EnglishEnrollment;
use App\Models\English\EnglishTeacher;
use App\Models\Student;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Every screen of the English section opens without an error, with real
 * data in it; and the switch between the sections is on every page.
 */
class EnglishPagesRenderTest extends EnglishPanelTestCase
{
    /**
     * @return array<string, array{0: callable(): string}>
     */
    public static function screens(): array
    {
        return [
            'home' => [fn (): string => Dashboard::getUrl(panel: 'english')],
            'students' => [fn (): string => EnglishStudentResource::getUrl(panel: 'english')],
            'students, incomplete profiles' => [fn (): string => EnglishStudentResource::getUrl('index', ['tab' => 'incomplete'], panel: 'english')],
            'new student' => [fn (): string => EnglishStudentResource::getUrl('create', panel: 'english')],
            'courses' => [fn (): string => EnglishCourseResource::getUrl(panel: 'english')],
            'new course' => [fn (): string => EnglishCourseResource::getUrl('create', panel: 'english')],
            'teachers' => [fn (): string => EnglishTeacherResource::getUrl(panel: 'english')],
            'levels' => [fn (): string => EnglishLevelResource::getUrl(panel: 'english')],
        ];
    }

    #[DataProvider('screens')]
    public function test_the_screen_opens(callable $url): void
    {
        $this->seedSomeEnglish();

        $this->get($url())->assertOk();
    }

    public function test_the_course_page_opens_with_its_halls_and_students(): void
    {
        $enrollment = $this->seedSomeEnglish();

        $this->get(EnglishCourseResource::getUrl('edit', ['record' => $enrollment->english_course_id], panel: 'english'))
            ->assertOk()
            ->assertSee($enrollment->course->name);
    }

    public function test_the_student_file_and_its_edit_page_open(): void
    {
        $enrollment = $this->seedSomeEnglish();

        $this->get(EnglishStudentResource::getUrl('view', ['record' => $enrollment->student_id], panel: 'english'))
            ->assertOk()
            ->assertSee($enrollment->student->full_name);

        // The student's courses are drawn after the page, with what was paid.
        Livewire::test(EnglishCoursesRelationManager::class, ['ownerRecord' => $enrollment->student, 'pageClass' => ViewEnglishStudent::class])
            ->assertCanSeeTableRecords([$enrollment])
            ->assertTableColumnStateSet('payments_sum_amount', '50,000 ل.س', $enrollment)
            ->assertTableColumnStateSet('remaining', '100,000 ل.س', $enrollment);

        $this->get(EnglishStudentResource::getUrl('edit', ['record' => $enrollment->student_id], panel: 'english'))->assertOk();
    }

    public function test_a_student_of_the_mosque_only_is_not_found_in_the_english_section(): void
    {
        $mosqueOnly = Student::factory()->create();

        $this->get(EnglishStudentResource::getUrl('view', ['record' => $mosqueOnly], panel: 'english'))->assertNotFound();
    }

    public function test_a_student_of_the_english_section_only_is_not_found_at_the_mosque(): void
    {
        $englishOnly = Student::factory()->english()->create();

        $this->get(StudentResource::getUrl('view', ['record' => $englishOnly], panel: 'admin'))->assertNotFound();
    }

    public function test_every_page_has_the_switch_between_the_sections(): void
    {
        $this->get(MosqueDashboard::getUrl(panel: 'admin'))
            ->assertOk()
            ->assertSeeInOrder(['class="rawda-sections"', 'دورات المسجد', 'دورة انجليزي'], escape: false)
            ->assertSee('class="rawda-sections__item is-current" aria-current="page">دورات المسجد</a>', escape: false)
            ->assertSee('href="'.url('/english').'"', escape: false);

        $this->get(Dashboard::getUrl(panel: 'english'))
            ->assertOk()
            ->assertSee('class="rawda-sections__item is-current" aria-current="page">دورة انجليزي</a>', escape: false)
            ->assertSee('href="'.url('/admin').'"', escape: false);
    }

    /**
     * A course with a hall, a teacher, and a student paying part of the price.
     */
    private function seedSomeEnglish(): EnglishEnrollment
    {
        $enrollment = $this->enrollInEnglish(Student::factory()->complete()->english()->create());
        $enrollment->hall->update(['teacher_id' => EnglishTeacher::factory()->create()->getKey()]);
        $this->alreadyPaid($enrollment, 50000);

        return $enrollment;
    }
}
