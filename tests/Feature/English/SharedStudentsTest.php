<?php

namespace Tests\Feature\English;

use App\Actions\Settings\RemindAboutIncompleteProfiles;
use App\Actions\Students\DeleteStudent;
use App\Actions\Students\SaveStudent;
use App\Enums\Section;
use App\Filament\Resources\Students\StudentResource;
use App\Models\English\EnglishCourse;
use App\Models\English\EnglishLevel;
use App\Models\Student;
use App\Models\TajweedLevel;
use App\Services\Statistics\DashboardFigures;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsDomain;
use Tests\Concerns\BuildsEnglish;
use Tests\TestCase;

/**
 * The students are shared by the two sections: one record per person,
 * on the list of each section they study in, with the level of each.
 */
class SharedStudentsTest extends TestCase
{
    use BuildsDomain;
    use BuildsEnglish;
    use RefreshDatabase;

    /**
     * @return array<string, mixed>
     */
    private function details(array $overrides = []): array
    {
        return $overrides + [
            'full_name' => 'سامر الأحمد',
            'birth_date' => '2012-03-04',
            'phone' => '0933111222',
            'guardian_phone' => '0944111222',
            'address' => 'الميدان',
        ];
    }

    public function test_a_student_added_in_the_english_section_is_on_its_list_only(): void
    {
        $level = EnglishLevel::query()->where('name', 'A2')->sole();

        $student = app(SaveStudent::class)->handle($this->details(['english_level_id' => $level->getKey()]), section: Section::English);

        $this->assertTrue($student->in_english);
        $this->assertFalse($student->in_mosque);
        $this->assertTrue($student->english_profile_complete);
        // The mosque asks for the tajweed level and the pages memorized before.
        $this->assertFalse($student->is_profile_complete);
        $this->assertSame($level->getKey(), $student->english_level_id);
    }

    public function test_a_student_added_at_the_mosque_is_on_the_mosque_list_only(): void
    {
        $student = app(SaveStudent::class)->handle($this->details([
            'tajweed_level_id' => TajweedLevel::factory()->create()->getKey(),
            'prior_memorized_pages' => '0',
        ]));

        $this->assertTrue($student->in_mosque);
        $this->assertFalse($student->in_english);
        $this->assertTrue($student->is_profile_complete);
        $this->assertFalse($student->english_profile_complete);
    }

    public function test_the_english_form_never_touches_what_the_mosque_wrote(): void
    {
        $tajweed = TajweedLevel::factory()->create();
        $student = Student::factory()->complete()->create(['tajweed_level_id' => $tajweed->getKey(), 'prior_memorized_pages' => 40, 'in_english' => true]);

        // The English form sends its own fields only.
        app(SaveStudent::class)->handle($this->details([
            'english_level_id' => EnglishLevel::query()->where('name', 'B1')->value('id'),
        ]), $student, Section::English);

        $student->refresh();
        $this->assertSame($tajweed->getKey(), $student->tajweed_level_id);
        $this->assertSame(40, $student->prior_memorized_pages);
        $this->assertTrue($student->in_mosque);
        $this->assertTrue($student->is_profile_complete);
        $this->assertTrue($student->english_profile_complete);
    }

    public function test_removing_a_phone_in_one_section_makes_the_profile_incomplete_in_both(): void
    {
        $student = Student::factory()->complete()->inBothSections()->create([
            'english_level_id' => EnglishLevel::query()->where('name', 'A1')->value('id'),
            'english_profile_complete' => true,
        ]);

        app(SaveStudent::class)->handle($this->details(['phone' => null]), $student, Section::English);

        $student->refresh();
        $this->assertFalse($student->is_profile_complete);
        $this->assertFalse($student->english_profile_complete);
    }

    public function test_a_mosque_student_enrolled_in_an_english_course_is_on_both_lists_and_keeps_everything(): void
    {
        $tajweed = TajweedLevel::factory()->create();
        $student = Student::factory()->complete()->create(['tajweed_level_id' => $tajweed->getKey()]);
        $mosqueEnrollment = $this->enroll($student);
        $course = EnglishCourse::factory()->create(['english_level_id' => EnglishLevel::query()->where('name', 'B2')->value('id')]);

        $this->enrollInEnglish($student, $course);

        $student->refresh();
        $this->assertTrue($student->in_mosque);
        $this->assertTrue($student->in_english);
        $this->assertSame($tajweed->getKey(), $student->tajweed_level_id);
        $this->assertSame(1, $student->enrollments()->count());
        $this->assertSame($mosqueEnrollment->card_code, $student->enrollments()->value('card_code'));
        // A student with no English level gets the course's, and the profile is then complete.
        $this->assertSame($course->english_level_id, $student->english_level_id);
        $this->assertTrue($student->english_profile_complete);
    }

    public function test_enrolling_does_not_change_an_english_level_the_student_already_has(): void
    {
        $a1 = EnglishLevel::query()->where('name', 'A1')->value('id');
        $student = Student::factory()->english()->create(['english_level_id' => $a1]);

        $this->enrollInEnglish($student, EnglishCourse::factory()->create(['english_level_id' => EnglishLevel::query()->where('name', 'C1')->value('id')]));

        $this->assertSame($a1, $student->refresh()->english_level_id);
    }

    public function test_an_english_student_enrolled_in_a_mosque_course_joins_the_mosque_list(): void
    {
        $student = Student::factory()->english()->create();

        $this->enroll($student);

        $student->refresh();
        $this->assertTrue($student->in_mosque);
        $this->assertTrue($student->in_english);
    }

    public function test_the_mosque_lists_and_figures_leave_out_the_english_only_students(): void
    {
        $mosque = Student::factory()->create(['full_name' => 'طالب المسجد']);
        $both = Student::factory()->inBothSections()->create(['full_name' => 'طالب القسمين']);
        Student::factory()->english()->count(3)->create();

        $this->assertEqualsCanonicalizing(
            [$mosque->getKey(), $both->getKey()],
            StudentResource::getEloquentQuery()->pluck('students.id')->all(),
        );
        $this->assertSame(2, app(DashboardFigures::class)->activeStudents());
        $this->assertSame(2, app(DashboardFigures::class)->incompleteProfiles());
    }

    public function test_the_mosque_reminder_counts_the_mosque_students_only(): void
    {
        Student::factory()->english()->count(2)->create();

        $this->assertFalse(app(RemindAboutIncompleteProfiles::class)->handle());

        Student::factory()->create();

        $this->assertTrue(app(RemindAboutIncompleteProfiles::class)->handle());
        $this->assertSame([1], $this->adminNotifier->incompleteProfileCounts);
    }

    public function test_a_student_of_both_sections_deleted_in_one_stays_in_the_other(): void
    {
        $student = Student::factory()->inBothSections()->create();

        $deleted = app(DeleteStudent::class)->handle($student, Section::English);

        $this->assertFalse($deleted);
        $student->refresh();
        $this->assertFalse($student->in_english);
        $this->assertTrue($student->in_mosque);

        $other = Student::factory()->inBothSections()->create();

        $this->assertFalse(app(DeleteStudent::class)->handle($other));
        $other->refresh();
        $this->assertFalse($other->in_mosque);
        $this->assertTrue($other->in_english);
    }

    public function test_a_student_of_one_section_with_no_records_is_deleted_for_good(): void
    {
        $student = Student::factory()->english()->create();

        $this->assertTrue(app(DeleteStudent::class)->handle($student, Section::English));

        $this->assertNull($student->fresh());
    }

    public function test_a_student_enrolled_in_an_english_course_is_not_deleted_from_the_english_list(): void
    {
        $enrollment = $this->enrollInEnglish();

        $this->assertViolates('student.has_records', fn () => app(DeleteStudent::class)->handle($enrollment->student, Section::English));

        $this->assertTrue($enrollment->student->fresh()->in_english);
    }

    public function test_a_mosque_student_with_english_courses_is_only_taken_off_the_mosque_list(): void
    {
        $student = Student::factory()->create();
        $this->enrollInEnglish($student);

        $this->assertFalse(app(DeleteStudent::class)->handle($student));

        $student->refresh();
        $this->assertFalse($student->in_mosque);
        $this->assertTrue($student->in_english);
    }
}
