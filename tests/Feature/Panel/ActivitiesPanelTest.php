<?php

namespace Tests\Feature\Panel;

use App\Actions\Students\ArchiveStudent;
use App\Enums\ActivityAudience;
use App\Enums\CourseStatus;
use App\Enums\PointSource;
use App\Filament\Resources\Activities\ActivityResource;
use App\Filament\Resources\Activities\Pages\CreateActivity;
use App\Filament\Resources\Activities\Pages\EditActivity;
use App\Filament\Resources\Activities\Pages\ListActivities;
use App\Filament\Resources\Students\Pages\ViewStudent;
use App\Filament\Resources\Students\RelationManagers\ActivitiesRelationManager;
use App\Filament\Support\Options;
use App\Models\Activity;
use App\Models\Circle;
use App\Models\Course;
use App\Models\Student;
use Filament\Actions\Testing\TestAction;
use Livewire\Livewire;

class ActivitiesPanelTest extends PanelTestCase
{
    private Course $course;

    private Circle $circle;

    protected function setUp(): void
    {
        parent::setUp();

        $this->course = Course::factory()->create(['name' => 'دورة الخريف', 'start_date' => '2026-09-01']);
        $this->circle = Circle::factory()->create(['course_id' => $this->course->getKey(), 'name' => 'حلقة النور']);
    }

    public function test_an_activity_of_a_circle_is_recorded_with_those_who_attended(): void
    {
        $came = $this->enroll(Student::factory()->create(['full_name' => 'أحمد الحموي']), $this->course, $this->circle);
        $absent = $this->enroll(Student::factory()->create(['full_name' => 'خالد الشامي']), $this->course, $this->circle);

        Livewire::test(CreateActivity::class)
            // The only running course is already chosen: the admin goes straight to the circle.
            ->assertSchemaStateSet(['audience' => ActivityAudience::Circle->value, 'course_id' => $this->course->getKey()])
            ->fillForm([
                'title' => 'مسابقة الحفظ',
                'activity_date' => '2026-10-06',
                'circle_id' => $this->circle->getKey(),
                'points' => '١٠',
            ])
            ->fillForm(['enrollment_ids' => [$came->getKey()]])
            ->call('create')
            ->assertHasNoFormErrors()
            ->assertRedirect(ActivityResource::getUrl());

        $activity = Activity::query()->sole();
        $this->assertSame(['مسابقة الحفظ', '2026-10-06', $this->circle->getKey(), 10], [
            $activity->title, $activity->activity_date->toDateString(), $activity->circle_id, $activity->points,
        ]);
        $this->assertSame([$came->getKey()], $activity->attendances()->pluck('enrollment_id')->all());
        $this->assertSame(PointSource::Activity, $came->pointTransactions()->sole()->source);
        $this->assertSame(0, $absent->pointTransactions()->count());
    }

    public function test_an_activity_of_the_whole_mosque_offers_every_student_of_a_running_course(): void
    {
        $here = $this->enroll(Student::factory()->create(['full_name' => 'أحمد الحموي']), $this->course, $this->circle);
        $there = $this->enroll(Student::factory()->create(['full_name' => 'سعيد الحلبي']), Course::factory()->create(['start_date' => '2026-09-01']));
        $finished = $this->enroll(course: Course::factory()->create(['start_date' => '2026-09-01']));
        $finished->course->update(['status' => CourseStatus::Finished]);

        $offered = Options::activityAttendees(ActivityAudience::Mosque, null);

        $this->assertEqualsCanonicalizing([$here->getKey(), $there->getKey()], array_keys($offered));
        $this->assertArrayNotHasKey($finished->getKey(), $offered);
        // Each choice says which circle and course, so that namesakes are told apart.
        $this->assertSame('أحمد الحموي — حلقة النور (دورة الخريف)', $offered[$here->getKey()]);

        Livewire::test(CreateActivity::class)
            ->fillForm(['audience' => ActivityAudience::Mosque->value])
            ->assertFormFieldHidden('circle_id')
            ->fillForm([
                'title' => 'رحلة الجامع',
                'activity_date' => '2026-10-05',
                'points' => '3',
                'enrollment_ids' => [$here->getKey(), $there->getKey()],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $activity = Activity::query()->sole();
        $this->assertNull($activity->circle_id);
        $this->assertSame(2, $activity->attendances()->count());
    }

    public function test_the_list_of_a_circle_offers_its_students_only(): void
    {
        $member = $this->enroll(Student::factory()->create(['full_name' => 'أحمد الحموي']), $this->course, $this->circle);
        $otherCircle = Circle::factory()->create(['course_id' => $this->course->getKey()]);
        $this->enroll(course: $this->course, circle: $otherCircle);
        $archived = $this->enroll(course: $this->course, circle: $this->circle);
        app(ArchiveStudent::class)->handle($archived->student);

        $this->assertSame(
            [$member->getKey() => 'أحمد الحموي'],
            Options::activityAttendees(ActivityAudience::Circle, $this->circle->getKey()),
        );
        $this->assertSame([], Options::activityAttendees(ActivityAudience::Circle, null));

        // Whoever is on the list of an activity stays on offer when it is opened again.
        $activity = Activity::factory()->create(['circle_id' => $this->circle->getKey()]);
        $activity->attendances()->create(['enrollment_id' => $archived->getKey()]);

        $this->assertArrayHasKey($archived->getKey(), Options::activityAttendees(ActivityAudience::Circle, $this->circle->getKey(), $activity));
    }

    public function test_changing_the_circle_starts_the_list_again(): void
    {
        $member = $this->enroll(course: $this->course, circle: $this->circle);
        $otherCircle = Circle::factory()->create(['course_id' => $this->course->getKey()]);

        Livewire::test(CreateActivity::class)
            ->fillForm(['circle_id' => $this->circle->getKey()])
            ->fillForm(['enrollment_ids' => [$member->getKey()]])
            ->fillForm(['circle_id' => $otherCircle->getKey()])
            ->assertSchemaStateSet(['enrollment_ids' => []]);
    }

    public function test_what_is_wrong_is_said_under_its_field_and_a_broken_rule_in_a_notification(): void
    {
        $late = $this->enroll(Student::factory()->create(['full_name' => 'عمر المتأخر']), $this->course, $this->circle, '2026-10-05');

        Livewire::test(CreateActivity::class)
            ->fillForm(['title' => '', 'circle_id' => $this->circle->getKey(), 'points' => ''])
            ->call('create')
            ->assertHasFormErrors(['title', 'points']);

        Livewire::test(CreateActivity::class)
            ->fillForm([
                'title' => 'لقاء',
                'activity_date' => '2026-10-01',
                'circle_id' => $this->circle->getKey(),
                'points' => '2',
            ])
            ->fillForm(['enrollment_ids' => [$late->getKey()]])
            ->call('create')
            ->assertNotified('عمر المتأخر: التاريخ قبل تسجيل الطالب في الدورة.');

        $this->assertSame(0, Activity::query()->count());
    }

    public function test_an_activity_is_opened_corrected_and_deleted(): void
    {
        $first = $this->enroll(course: $this->course, circle: $this->circle);
        $second = $this->enroll(course: $this->course, circle: $this->circle);
        $activity = $this->holdActivity([$first], $this->circle, ['title' => 'رحلة', 'activity_date' => '2026-10-04']);

        Livewire::test(EditActivity::class, ['record' => $activity->getRouteKey()])
            ->assertSchemaStateSet([
                'title' => 'رحلة',
                'audience' => ActivityAudience::Circle->value,
                'course_id' => $this->course->getKey(),
                'circle_id' => $this->circle->getKey(),
                'enrollment_ids' => [$first->getKey()],
            ])
            ->fillForm(['points' => '9', 'enrollment_ids' => [$first->getKey(), $second->getKey()]])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(2, $activity->attendances()->count());
        $this->assertSame(9, (int) $second->pointTransactions()->sum('points'));

        Livewire::test(EditActivity::class, ['record' => $activity->getRouteKey()])
            ->callAction('delete')
            ->assertRedirect();

        $this->assertSame(0, Activity::query()->count());
        $this->assertSame(0, $first->pointTransactions()->count());
    }

    public function test_the_list_shows_who_each_activity_was_for_and_how_many_came(): void
    {
        $member = $this->enroll(course: $this->course, circle: $this->circle);
        $forCircle = $this->holdActivity([$member], $this->circle, ['title' => 'مسابقة الحلقة']);
        $forMosque = $this->holdActivity([], overrides: ['title' => 'إفطار الجامع']);

        Livewire::test(ListActivities::class)
            ->assertCanSeeTableRecords([$forCircle, $forMosque])
            ->assertSee('حلقة النور (دورة الخريف)')
            ->assertSee(ActivityAudience::Mosque->label())
            ->filterTable('audience', ActivityAudience::Mosque->value)
            ->assertCanSeeTableRecords([$forMosque])
            ->assertCanNotSeeTableRecords([$forCircle])
            ->resetTableFilters()
            ->filterTable('course', $this->course->getKey())
            ->assertCanSeeTableRecords([$forCircle])
            ->assertCanNotSeeTableRecords([$forMosque])
            ->resetTableFilters()
            ->callAction(TestAction::make('delete')->table($forCircle));

        $this->assertSame([$forMosque->getKey()], Activity::query()->pluck('id')->all());
        $this->assertSame(0, $member->pointTransactions()->count());
    }

    public function test_the_file_of_a_student_lists_the_activities_they_attended(): void
    {
        $enrollment = $this->enroll(course: $this->course, circle: $this->circle);
        $this->holdActivity([$enrollment], $this->circle, ['title' => 'مسابقة الحلقة', 'points' => 0]);
        $this->holdActivity([$enrollment], overrides: ['title' => 'إفطار الجامع', 'activity_date' => '2026-10-02']);
        $this->holdActivity([], overrides: ['title' => 'نشاط لم يحضره']);

        Livewire::test(ActivitiesRelationManager::class, ['ownerRecord' => $enrollment->student, 'pageClass' => ViewStudent::class])
            ->assertSuccessful()
            ->assertSee('مسابقة الحلقة')
            ->assertSee('إفطار الجامع')
            ->assertDontSee('نشاط لم يحضره')
            ->assertCountTableRecords(2);
    }
}
