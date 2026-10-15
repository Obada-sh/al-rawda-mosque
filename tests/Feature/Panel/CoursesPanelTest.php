<?php

namespace Tests\Feature\Panel;

use App\Enums\CourseStatus;
use App\Enums\SessionStatus;
use App\Enums\Weekday;
use App\Filament\Resources\Courses\Pages\CreateCourse;
use App\Filament\Resources\Courses\Pages\EditCourse;
use App\Filament\Resources\Courses\Pages\ListCourses;
use App\Filament\Resources\Courses\RelationManagers\CirclesRelationManager;
use App\Filament\Resources\Courses\RelationManagers\EnrollmentsRelationManager;
use App\Filament\Resources\Courses\RelationManagers\SessionsRelationManager;
use App\Filament\Resources\Courses\RelationManagers\TransfersRelationManager;
use App\Models\Circle;
use App\Models\Course;
use App\Models\CourseSession;
use App\Models\Enrollment;
use App\Models\Student;
use App\Models\Teacher;
use Filament\Actions\Testing\TestAction;
use Livewire\Livewire;

class CoursesPanelTest extends PanelTestCase
{
    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function courseForm(array $overrides = []): array
    {
        return $overrides + [
            'name' => 'دورة الخريف',
            'start_date' => '2026-09-01',
            'end_date' => '2026-12-31',
            'points_per_page' => '2',
            'points_per_attendance' => '1',
        ];
    }

    /**
     * The hour and minute of a time field, whatever form it is kept in.
     */
    private function clock(mixed $time): string
    {
        preg_match('/\d{2}:\d{2}/', (string) $time, $matches);

        return $matches[0] ?? '';
    }

    /**
     * @return array{ownerRecord: Course, pageClass: class-string}
     */
    private function on(Course $course): array
    {
        return ['ownerRecord' => $course, 'pageClass' => EditCourse::class];
    }

    public function test_a_course_is_created_with_its_weekly_timetable(): void
    {
        Livewire::test(CreateCourse::class)
            ->fillForm($this->courseForm())
            ->set('data.schedules', [
                'row-a' => ['weekday' => Weekday::Saturday->value, 'start_time' => '16:00', 'end_time' => '18:30'],
                'row-b' => ['weekday' => Weekday::Monday->value, 'start_time' => '17:00', 'end_time' => '19:00'],
            ])
            ->call('create')
            ->assertHasNoFormErrors()
            ->assertRedirect();

        $course = Course::query()->with('schedules')->sole();
        $this->assertSame('دورة الخريف', $course->name);
        $this->assertSame(CourseStatus::Active, $course->status);
        $this->assertSame('2026-09-01', $course->start_date->toDateString());
        $this->assertSame(2, $course->points_per_page);
        $this->assertEqualsCanonicalizing(
            [[Weekday::Saturday, '16:00:00', '18:30:00'], [Weekday::Monday, '17:00:00', '19:00:00']],
            $course->schedules->map(fn ($schedule): array => [$schedule->weekday, $schedule->start_time, $schedule->end_time])->all(),
        );
    }

    public function test_course_validation_messages_land_on_the_right_fields_and_timetable_rows(): void
    {
        Livewire::test(CreateCourse::class)
            ->fillForm($this->courseForm(['name' => '', 'end_date' => '2026-08-01']))
            ->set('data.schedules', [
                'row-a' => ['weekday' => Weekday::Saturday->value, 'start_time' => '16:00', 'end_time' => '18:00'],
                'row-b' => ['weekday' => Weekday::Monday->value, 'start_time' => '19:00', 'end_time' => '17:00'],
            ])
            ->call('create')
            ->assertHasFormErrors(['name', 'end_date', 'schedules.row-b.end_time'])
            ->assertHasNoFormErrors(['schedules.row-a.end_time']);

        Livewire::test(CreateCourse::class)
            ->fillForm($this->courseForm())
            ->set('data.schedules', [])
            ->call('create')
            ->assertHasFormErrors(['schedules']);

        $this->assertSame(0, Course::query()->count());
    }

    public function test_the_edit_page_shows_the_timetable_in_week_order_and_saves_changes(): void
    {
        $course = Course::factory()->meetingOn([Weekday::Monday, Weekday::Saturday])->create([
            'name' => 'دورة الشتاء',
            'start_date' => '2026-09-01',
        ]);

        $page = Livewire::test(EditCourse::class, ['record' => $course->getRouteKey()])
            ->assertFormSet(['name' => 'دورة الشتاء', 'start_date' => '2026-09-01', 'status' => CourseStatus::Active->value]);

        $rows = array_values($page->get('data.schedules'));
        $this->assertSame(
            [[Weekday::Saturday->value, '16:00', '19:00'], [Weekday::Monday->value, '16:00', '19:00']],
            array_map(fn (array $row): array => [(int) $row['weekday'], $this->clock($row['start_time']), $this->clock($row['end_time'])], $rows),
        );

        $page
            ->fillForm(['name' => 'دورة الشتاء المكثفة', 'status' => CourseStatus::Finished->value])
            ->set('data.schedules', [
                'only' => ['weekday' => Weekday::Friday->value, 'start_time' => '09:00', 'end_time' => '11:00'],
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $course->refresh()->load('schedules');
        $this->assertSame('دورة الشتاء المكثفة', $course->name);
        $this->assertSame(CourseStatus::Finished, $course->status);
        $this->assertSame([Weekday::Friday], $course->schedules->pluck('weekday')->all());
    }

    public function test_a_used_course_is_not_deleted_and_an_unused_one_is(): void
    {
        $used = $this->enroll()->course;
        $unused = Course::factory()->create();

        Livewire::test(EditCourse::class, ['record' => $used->getRouteKey()])
            ->callAction('delete')
            ->assertNotified('لا يمكن حذف دورة فيها طلاب أو جلسات أو نشاطات أو أسواق نقاط. يمكنك إنهاؤها بدلاً من ذلك.');

        Livewire::test(EditCourse::class, ['record' => $unused->getRouteKey()])
            ->callAction('delete')
            ->assertRedirect();

        $this->assertNotNull($used->fresh());
        $this->assertNull($unused->fresh());
    }

    public function test_the_courses_list_shows_days_circles_and_current_students(): void
    {
        $course = Course::factory()->meetingOn([Weekday::Wednesday, Weekday::Saturday])->create();
        $this->enroll(course: $course);

        Livewire::test(ListCourses::class)
            ->assertCanSeeTableRecords([$course])
            ->assertTableColumnStateSet('days', 'السبت، الأربعاء', $course)
            ->assertTableColumnStateSet('circles_count', 1, $course)
            ->assertTableColumnStateSet('enrollments_count', 1, $course);
    }

    public function test_the_admin_creates_circles_and_picks_their_teachers_from_the_list(): void
    {
        $course = Course::factory()->create();
        $supervisor = Teacher::factory()->create();
        $assistant = Teacher::factory()->create();

        Livewire::test(CirclesRelationManager::class, $this->on($course))
            ->callAction(TestAction::make('create')->table(), data: ['name' => 'حلقة الفجر'])
            ->assertHasNoActionErrors();

        $circle = Circle::query()->sole();
        $this->assertSame($course->getKey(), $circle->course_id);
        $this->assertNull($circle->supervisor_teacher_id);

        Livewire::test(CirclesRelationManager::class, $this->on($course))
            ->callAction(TestAction::make('edit')->table($circle), data: [
                'supervisor_teacher_id' => $supervisor->getKey(),
                'assistant_teacher_id' => $assistant->getKey(),
            ])
            ->assertHasNoActionErrors();

        $circle->refresh();
        $this->assertSame($supervisor->getKey(), $circle->supervisor_teacher_id);
        $this->assertSame($assistant->getKey(), $circle->assistant_teacher_id);

        Livewire::test(CirclesRelationManager::class, $this->on($course))
            ->callAction(TestAction::make('create')->table(), data: ['name' => 'حلقة الفجر'])
            ->assertHasActionErrors(['name']);

        Livewire::test(CirclesRelationManager::class, $this->on($course))
            ->callAction(TestAction::make('edit')->table($circle), data: ['assistant_teacher_id' => $supervisor->getKey()])
            ->assertHasActionErrors(['assistant_teacher_id']);
    }

    public function test_a_circle_with_students_is_kept_and_an_empty_one_is_deleted(): void
    {
        $enrollment = $this->enroll();
        $empty = Circle::factory()->create(['course_id' => $enrollment->course_id]);

        Livewire::test(CirclesRelationManager::class, $this->on($enrollment->course))
            ->callAction(TestAction::make('delete')->table($enrollment->circle))
            ->assertNotified('لا يمكن حذف حلقة فيها طلاب أو لها سجل تنقلات أو نشاطات. يمكنك تعطيلها بدلاً من ذلك.');

        Livewire::test(CirclesRelationManager::class, $this->on($enrollment->course))
            ->callAction(TestAction::make('delete')->table($empty));

        $this->assertNotNull($enrollment->circle->fresh());
        $this->assertNull($empty->fresh());
    }

    public function test_a_student_is_enrolled_from_the_course_and_the_circle_must_be_chosen(): void
    {
        $course = Course::factory()->create();
        $circle = Circle::factory()->create(['course_id' => $course->getKey()]);
        $student = Student::factory()->create();

        Livewire::test(EnrollmentsRelationManager::class, $this->on($course))
            ->callAction(TestAction::make('enroll')->table(), data: ['student_id' => $student->getKey()])
            ->assertHasActionErrors(['circle_id']);

        Livewire::test(EnrollmentsRelationManager::class, $this->on($course))
            ->callAction(TestAction::make('enroll')->table(), data: [
                'student_id' => $student->getKey(),
                'circle_id' => $circle->getKey(),
            ])
            ->assertHasNoActionErrors()
            ->assertNotified('تم تسجيل الطالب');

        $enrollment = Enrollment::query()->sole();
        $this->assertSame($circle->getKey(), $enrollment->circle_id);
        $this->assertSame('2026-10-07', $enrollment->enrolled_on->toDateString());
        $this->assertMatchesRegularExpression('/^[0-9]{10}$/', $enrollment->card_code);

        Livewire::test(EnrollmentsRelationManager::class, $this->on($course))
            ->assertCanSeeTableRecords([$enrollment])
            ->assertActionExists(TestAction::make('transfer')->table($enrollment))
            ->assertActionExists(TestAction::make('card')->table($enrollment));
    }

    public function test_students_cannot_be_enrolled_in_a_finished_course(): void
    {
        $course = Course::factory()->finished()->create();

        Livewire::test(EnrollmentsRelationManager::class, $this->on($course))
            ->assertActionHidden(TestAction::make('enroll')->table());
    }

    public function test_a_student_is_transferred_from_the_course_and_the_log_shows_it(): void
    {
        $course = Course::factory()->create();
        $from = Circle::factory()->create(['course_id' => $course->getKey(), 'name' => 'حلقة أ']);
        $to = Circle::factory()->create(['course_id' => $course->getKey(), 'name' => 'حلقة ب']);
        $enrollment = $this->enroll(course: $course, circle: $from);

        Livewire::test(EnrollmentsRelationManager::class, $this->on($course))
            ->callAction(TestAction::make('transfer')->table($enrollment), data: [
                'to_circle_id' => $to->getKey(),
                'transferred_on' => '2026-10-06',
            ])
            ->assertHasNoActionErrors();

        $this->assertSame($to->getKey(), $enrollment->fresh()->circle_id);

        Livewire::test(TransfersRelationManager::class, $this->on($course))
            ->assertCountTableRecords(1)
            ->assertSee('حلقة أ')
            ->assertSee('حلقة ب')
            ->assertSee($enrollment->student->full_name);
    }

    public function test_an_extra_session_is_opened_and_a_session_is_cancelled_and_reopened(): void
    {
        $course = Course::factory()->meetingOn([Weekday::Wednesday])->create();

        Livewire::test(SessionsRelationManager::class, $this->on($course))
            ->callAction(TestAction::make('open')->table(), data: ['session_date' => '2026-10-05'])
            ->assertHasNoActionErrors()
            ->assertNotified('تم فتح الجلسة');

        $session = CourseSession::query()->sole();
        $this->assertSame('2026-10-05', $session->session_date->toDateString());

        Livewire::test(SessionsRelationManager::class, $this->on($course))
            ->callAction(TestAction::make('open')->table(), data: ['session_date' => '2026-10-05'])
            ->assertNotified('توجد جلسة في هذا التاريخ.');

        Livewire::test(SessionsRelationManager::class, $this->on($course))
            ->callAction(TestAction::make('cancel')->table($session))
            ->assertNotified('تم إلغاء الجلسة');

        $this->assertSame(SessionStatus::Cancelled, $session->fresh()->status);

        Livewire::test(SessionsRelationManager::class, $this->on($course))
            ->callAction(TestAction::make('reopen')->table($session))
            ->assertNotified('تمت إعادة فتح الجلسة');

        $this->assertSame(SessionStatus::Held, $session->fresh()->status);
    }

    public function test_a_session_with_attendance_is_not_cancelled(): void
    {
        $enrollment = $this->enroll();
        $this->attend($enrollment);
        $session = CourseSession::query()->sole();

        Livewire::test(SessionsRelationManager::class, $this->on($enrollment->course))
            ->callAction(TestAction::make('cancel')->table($session))
            ->assertNotified('لا يمكن إلغاء جلسة فيها حضور مسجَّل. احذف الحضور أولاً.');

        $this->assertSame(SessionStatus::Held, $session->fresh()->status);
    }
}
