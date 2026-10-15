<?php

namespace Tests\Feature\Panel;

use App\Actions\Enrollments\UpdateEnrollmentDates;
use App\Filament\Resources\Courses\Pages\EditCourse;
use App\Filament\Resources\Courses\Pages\ListCourses;
use App\Filament\Resources\Students\Pages\ListStudents;
use App\Filament\Support\Cards\CardPrintActions;
use App\Models\Circle;
use App\Models\Course;
use App\Models\Student;
use Filament\Actions\Testing\TestAction;
use Illuminate\Support\Js;
use Livewire\Livewire;

/**
 * The card printing page can be opened from where the admin looks for
 * it: the students' list (the whole course, a circle, the ticked
 * students, one student's row), the list of courses and a course's page.
 */
class CardPrintButtonsTest extends PanelTestCase
{
    private function opens(string $url): string
    {
        return 'window.open('.Js::from($url).", '_blank')";
    }

    public function test_the_students_list_prints_the_cards_of_a_course_or_of_one_circle(): void
    {
        $course = Course::factory()->create();
        $circle = Circle::factory()->create(['course_id' => $course->getKey()]);

        Livewire::test(ListStudents::class)
            // The only running course is already chosen.
            ->mountAction('printCards')
            ->assertSchemaStateSet(['course_id' => $course->getKey()])
            ->callMountedAction()
            ->assertHasNoActionErrors()
            ->assertJs($this->opens(CardPrintActions::url($course->getKey())));

        Livewire::test(ListStudents::class)
            ->callAction('printCards', data: ['course_id' => $course->getKey(), 'circle_id' => $circle->getKey()])
            ->assertJs($this->opens(CardPrintActions::url($course->getKey(), $circle->getKey())));

        Livewire::test(ListStudents::class)
            ->callAction('printCards', data: ['course_id' => null])
            ->assertHasActionErrors(['course_id']);
    }

    public function test_a_students_row_opens_the_card_of_the_course_they_are_in_now(): void
    {
        $enrolled = $this->enroll(Student::factory()->create(['full_name' => 'أحمد']));
        $left = $this->enroll(Student::factory()->create(['full_name' => 'خالد']));
        app(UpdateEnrollmentDates::class)->handle($left, [
            'enrolled_on' => $left->enrolled_on->toDateString(),
            'left_on' => now()->toDateString(),
        ]);
        $neverEnrolled = Student::factory()->create(['full_name' => 'بلال']);

        Livewire::test(ListStudents::class)
            ->assertActionHasUrl(
                TestAction::make('card')->table($enrolled->student),
                CardPrintActions::url($enrolled->course_id, enrollmentIds: [$enrolled->getKey()]),
            )
            ->assertActionHidden(TestAction::make('card')->table($left->student))
            ->assertActionHidden(TestAction::make('card')->table($neverEnrolled));
    }

    public function test_the_ticked_students_get_their_cards_in_the_chosen_course(): void
    {
        $course = Course::factory()->create();
        $first = $this->enroll(course: $course);
        $second = $this->enroll(course: $course);
        // A student of the course who is not ticked gets no card.
        $this->enroll(course: $course);
        $otherCourse = $this->enroll();

        Livewire::test(ListStudents::class)
            ->selectTableRecords([$first->student, $second->student, $otherCourse->student])
            ->callAction(TestAction::make('printCards')->table()->bulk(), data: ['course_id' => $course->getKey()])
            ->assertHasNoActionErrors()
            ->assertJs($this->opens(CardPrintActions::url($course->getKey(), enrollmentIds: [$first->getKey(), $second->getKey()])));

        // Nobody ticked is in that course: nothing opens, and the admin is told why.
        Livewire::test(ListStudents::class)
            ->selectTableRecords([$otherCourse->student])
            ->callAction(TestAction::make('printCards')->table()->bulk(), data: ['course_id' => $course->getKey()])
            ->assertNotified('لا أحد من الطلاب المحدَّدين مسجَّل في هذه الدورة.')
            ->assertNoJs();
    }

    public function test_the_courses_list_and_a_courses_page_print_its_students_and_teachers_cards(): void
    {
        $course = Course::factory()->create();

        Livewire::test(ListCourses::class)
            ->assertActionHasUrl(TestAction::make('cards')->table($course), CardPrintActions::url($course->getKey()))
            ->assertActionHasUrl(TestAction::make('teacherCards')->table($course), CardPrintActions::teachersUrl($course->getKey()));

        Livewire::test(EditCourse::class, ['record' => $course->getRouteKey()])
            ->assertActionHasUrl('printCards', CardPrintActions::url($course->getKey()))
            ->assertActionHasUrl('printTeacherCards', CardPrintActions::teachersUrl($course->getKey()));
    }
}
