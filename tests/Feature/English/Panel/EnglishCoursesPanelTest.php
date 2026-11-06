<?php

namespace Tests\Feature\English\Panel;

use App\Enums\Weekday;
use App\Filament\English\Resources\EnglishCourses\Pages\CreateEnglishCourse;
use App\Filament\English\Resources\EnglishCourses\Pages\EditEnglishCourse;
use App\Filament\English\Resources\EnglishCourses\Pages\ListEnglishCourses;
use App\Filament\English\Resources\EnglishCourses\RelationManagers\EnrollmentsRelationManager;
use App\Filament\English\Resources\EnglishCourses\RelationManagers\HallsRelationManager;
use App\Filament\English\Resources\EnglishLevels\Pages\ManageEnglishLevels;
use App\Filament\English\Resources\EnglishStudents\Pages\CreateEnglishStudent;
use App\Filament\English\Resources\EnglishStudents\Pages\ListEnglishStudents;
use App\Filament\English\Resources\EnglishTeachers\Pages\ManageEnglishTeachers;
use App\Filament\Resources\Students\Pages\ListStudents;
use App\Models\English\EnglishCourse;
use App\Models\English\EnglishEnrollment;
use App\Models\English\EnglishHall;
use App\Models\English\EnglishLevel;
use App\Models\English\EnglishTeacher;
use App\Models\Student;
use App\Models\TajweedLevel;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Tests\Support\Pictures;

class EnglishCoursesPanelTest extends EnglishPanelTestCase
{
    /**
     * @return array{ownerRecord: EnglishCourse, pageClass: class-string}
     */
    private function on(EnglishCourse $course): array
    {
        return ['ownerRecord' => $course, 'pageClass' => EditEnglishCourse::class];
    }

    public function test_a_course_is_created_with_its_level_price_and_class_days(): void
    {
        $b1 = EnglishLevel::query()->where('name', 'B1')->value('id');

        Livewire::test(CreateEnglishCourse::class)
            ->fillForm([
                'name' => 'B1 مسائي',
                'english_level_id' => $b1,
                'price' => '250000',
                'start_date' => '2026-10-01',
            ])
            ->set('data.schedules', [
                'row-a' => ['weekday' => Weekday::Saturday->value, 'start_time' => '17:00', 'end_time' => '19:00'],
            ])
            ->call('create')
            ->assertHasNoFormErrors()
            ->assertRedirect();

        $course = EnglishCourse::query()->with('schedules')->sole();
        $this->assertSame(250000, $course->price);
        $this->assertSame($b1, $course->english_level_id);
        $this->assertSame([Weekday::Saturday], $course->schedules->pluck('weekday')->all());

        Livewire::test(ListEnglishCourses::class)
            ->assertCanSeeTableRecords([$course])
            ->assertTableColumnStateSet('days', 'السبت', $course);
    }

    public function test_course_validation_messages_land_on_their_fields(): void
    {
        Livewire::test(CreateEnglishCourse::class)
            ->fillForm(['name' => 'A1', 'price' => '150,000', 'start_date' => '2026-10-01'])
            ->set('data.schedules', [
                'row-a' => ['weekday' => Weekday::Saturday->value, 'start_time' => '19:00', 'end_time' => '17:00'],
            ])
            ->call('create')
            ->assertHasFormErrors(['price', 'english_level_id', 'schedules.row-a.end_time']);

        $this->assertSame(0, EnglishCourse::query()->count());
    }

    public function test_halls_are_added_with_their_teacher_and_assistant(): void
    {
        $course = EnglishCourse::factory()->create();
        $teacher = EnglishTeacher::factory()->create();
        $assistant = EnglishTeacher::factory()->create();

        Livewire::test(HallsRelationManager::class, $this->on($course))
            ->callAction(TestAction::make('create')->table(), data: [
                'name' => 'القاعة الزرقاء',
                'teacher_id' => $teacher->getKey(),
                'assistant_id' => $assistant->getKey(),
            ])
            ->assertHasNoActionErrors();

        $hall = EnglishHall::query()->sole();
        $this->assertSame([$course->getKey(), $teacher->getKey(), $assistant->getKey()], [$hall->english_course_id, $hall->teacher_id, $hall->assistant_id]);

        Livewire::test(HallsRelationManager::class, $this->on($course))
            ->callAction(TestAction::make('create')->table(), data: ['name' => 'القاعة الزرقاء'])
            ->assertHasActionErrors(['name']);
    }

    public function test_a_mosque_student_is_found_and_enrolled_without_being_entered_again(): void
    {
        $course = EnglishCourse::factory()->create(['price' => 200000]);
        $hall = EnglishHall::factory()->create(['english_course_id' => $course->getKey()]);
        $mosqueStudent = Student::factory()->complete()->create(['full_name' => 'عمر المصري', 'tajweed_level_id' => TajweedLevel::factory()->create()->getKey()]);

        Livewire::test(EnrollmentsRelationManager::class, $this->on($course))
            ->callAction(TestAction::make('enroll')->table(), data: [
                'student_id' => $mosqueStudent->getKey(),
                'english_hall_id' => $hall->getKey(),
                'enrolled_on' => '2026-10-07',
                'price' => '150000',
                'price_note' => 'خصم أخوة',
            ])
            ->assertHasNoActionErrors();

        $enrollment = EnglishEnrollment::query()->sole();
        $this->assertSame($mosqueStudent->getKey(), $enrollment->student_id);
        $this->assertSame(150000, $enrollment->duePrice());
        $this->assertSame(1, Student::query()->count());
        $this->assertTrue($mosqueStudent->fresh()->in_english);
        $this->assertTrue($mosqueStudent->fresh()->in_mosque);

        Livewire::test(EnrollmentsRelationManager::class, $this->on($course))
            ->assertCanSeeTableRecords([$enrollment])
            ->assertTableColumnStateSet('remaining', '150,000 ل.س', $enrollment);
    }

    public function test_a_special_price_without_its_reason_is_refused_under_the_field(): void
    {
        $course = EnglishCourse::factory()->create();
        $hall = EnglishHall::factory()->create(['english_course_id' => $course->getKey()]);

        Livewire::test(EnrollmentsRelationManager::class, $this->on($course))
            ->callAction(TestAction::make('enroll')->table(), data: [
                'student_id' => Student::factory()->english()->create()->getKey(),
                'english_hall_id' => $hall->getKey(),
                'enrolled_on' => '2026-10-07',
                'price' => '100000',
            ])
            ->assertHasActionErrors(['price_note']);

        $this->assertSame(0, EnglishEnrollment::query()->count());
    }

    public function test_a_new_student_is_registered_straight_into_the_course(): void
    {
        $course = EnglishCourse::factory()->create();
        $hall = EnglishHall::factory()->create(['english_course_id' => $course->getKey()]);

        Livewire::test(EnrollmentsRelationManager::class, $this->on($course))
            ->mountAction(TestAction::make('register')->table())
            ->assertSchemaStateSet(['english_level_id' => $course->english_level_id, 'enrolled_on' => '2026-10-07'])
            ->fillForm([
                'full_name' => 'جود الخطيب',
                'father_work' => 'طبيب',
                'guardian_phone' => '0944555666',
                'english_hall_id' => $hall->getKey(),
            ])
            ->callMountedAction()
            ->assertHasNoActionErrors();

        $student = Student::query()->sole();
        $this->assertSame('جود الخطيب', $student->full_name);
        $this->assertSame('طبيب', $student->father_work);
        $this->assertFalse($student->in_mosque);
        $this->assertSame($hall->getKey(), $student->englishEnrollments()->value('english_hall_id'));
    }

    public function test_the_price_of_an_enrolled_student_is_changed_from_the_list(): void
    {
        $enrollment = $this->enrollInEnglish(course: EnglishCourse::factory()->create(['price' => 200000]));

        Livewire::test(EnrollmentsRelationManager::class, $this->on($enrollment->course))
            ->callAction(TestAction::make('price')->table($enrollment), data: ['price' => '120000', 'price_note' => 'يتيم'])
            ->assertHasNoActionErrors();

        $this->assertSame(120000, $enrollment->fresh()->duePrice());
    }

    public function test_each_section_lists_its_own_students(): void
    {
        $mosque = Student::factory()->create();
        $both = Student::factory()->inBothSections()->create();
        $english = Student::factory()->english()->create();

        Livewire::test(ListEnglishStudents::class)
            ->assertCanSeeTableRecords([$both, $english])
            ->assertCanNotSeeTableRecords([$mosque]);

        Filament::setCurrentPanel('admin');

        Livewire::test(ListStudents::class)
            ->assertCanSeeTableRecords([$mosque, $both])
            ->assertCanNotSeeTableRecords([$english]);
    }

    public function test_a_student_is_added_from_the_english_form_with_the_english_level(): void
    {
        Livewire::test(CreateEnglishStudent::class)
            ->assertFormFieldExists('english_level_id')
            ->assertFormFieldDoesNotExist('tajweed_level_id')
            ->assertFormFieldDoesNotExist('prior_memorized_pages')
            ->assertFormFieldExists('father_work')
            ->fillForm(['full_name' => 'ريم عبد الله', 'english_level_id' => EnglishLevel::query()->where('name', 'C1')->value('id')])
            ->call('create')
            ->assertHasNoFormErrors();

        $student = Student::query()->sole();
        $this->assertTrue($student->in_english);
        $this->assertFalse($student->in_mosque);
        $this->assertSame('C1', $student->englishLevel->name);
    }

    public function test_teachers_and_level_designs_are_managed_from_their_pages(): void
    {
        Livewire::test(ManageEnglishTeachers::class)
            ->callAction('create', data: ['full_name' => 'Mr. Adam', 'phone' => '0933222333'])
            ->assertHasNoActionErrors();

        $this->assertSame('Mr. Adam', EnglishTeacher::query()->sole()->full_name);

        $level = EnglishLevel::query()->where('name', 'A1')->sole();

        Livewire::test(ManageEnglishLevels::class)
            ->callAction(TestAction::make('edit')->table($level), data: ['card_background' => Pictures::png('a1.png')])
            ->assertHasNoActionErrors();

        $this->assertNotNull($level->fresh()->card_background_path);
    }
}
