<?php

namespace Tests\Feature\Panel;

use App\Actions\Attendance\RecordManualTeacherAttendance;
use App\Actions\Enrollments\TransferStudent;
use App\Filament\Pages\Backups;
use App\Filament\Pages\Dashboard;
use App\Filament\Pages\ManageSettings;
use App\Filament\Pages\Reports\CircleSheets;
use App\Filament\Pages\Reports\FinanceReport;
use App\Filament\Pages\Reports\KnightRanking;
use App\Filament\Pages\Reports\Statistics;
use App\Filament\Pages\ScanAttendance;
use App\Filament\Resources\Activities\ActivityResource;
use App\Filament\Resources\Attendances\AttendanceResource;
use App\Filament\Resources\Courses\CourseResource;
use App\Filament\Resources\Departments\DepartmentResource;
use App\Filament\Resources\Expenses\ExpenseResource;
use App\Filament\Resources\JuzExams\JuzExamResource;
use App\Filament\Resources\PointTransactions\PointTransactionResource;
use App\Filament\Resources\Recitations\RecitationResource;
use App\Filament\Resources\Students\StudentResource;
use App\Filament\Resources\TajweedLevels\TajweedLevelResource;
use App\Filament\Resources\TeacherAttendances\TeacherAttendanceResource;
use App\Filament\Resources\Teachers\TeacherResource;
use App\Models\Activity;
use App\Models\Circle;
use App\Models\Course;
use App\Models\Student;
use App\Models\Teacher;
use Filament\Facades\Filament;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Every screen of the panel opens without an error, with real data in it.
 */
class PagesRenderTest extends PanelTestCase
{
    /**
     * @return array<string, array{0: callable(): string}>
     */
    public static function screens(): array
    {
        return [
            'home' => [fn (): string => Dashboard::getUrl()],
            'scan attendance' => [fn (): string => ScanAttendance::getUrl()],
            'settings' => [fn (): string => ManageSettings::getUrl()],
            'my account' => [fn (): string => (string) Filament::getDefaultPanel()->getProfileUrl()],
            'students' => [fn (): string => StudentResource::getUrl()],
            'students, incomplete profiles' => [fn (): string => StudentResource::getUrl('index', ['tab' => 'incomplete'])],
            'students, archived' => [fn (): string => StudentResource::getUrl('index', ['tab' => 'archived'])],
            'new student' => [fn (): string => StudentResource::getUrl('create')],
            'courses' => [fn (): string => CourseResource::getUrl()],
            'new course' => [fn (): string => CourseResource::getUrl('create')],
            'teachers' => [fn (): string => TeacherResource::getUrl()],
            'tajweed levels' => [fn (): string => TajweedLevelResource::getUrl()],
            'attendance register' => [fn (): string => AttendanceResource::getUrl()],
            'teachers attendance' => [fn (): string => TeacherAttendanceResource::getUrl()],
            'recitations' => [fn (): string => RecitationResource::getUrl()],
            'juz exams' => [fn (): string => JuzExamResource::getUrl()],
            'juz exams, done' => [fn (): string => JuzExamResource::getUrl('index', ['tab' => 'done'])],
            'activities' => [fn (): string => ActivityResource::getUrl()],
            'new activity' => [fn (): string => ActivityResource::getUrl('create')],
            'points' => [fn (): string => PointTransactionResource::getUrl()],
            'statistics' => [fn (): string => Statistics::getUrl()],
            'quran knight' => [fn (): string => KnightRanking::getUrl()],
            'circle sheets' => [fn (): string => CircleSheets::getUrl()],
            'expenses' => [fn (): string => ExpenseResource::getUrl()],
            'expenses report' => [fn (): string => FinanceReport::getUrl()],
            'departments' => [fn (): string => DepartmentResource::getUrl()],
            'backups' => [fn (): string => Backups::getUrl()],
        ];
    }

    #[DataProvider('screens')]
    public function test_the_screen_opens(callable $url): void
    {
        $this->seedSomeActivity();

        $this->get($url())->assertOk();
    }

    public function test_the_student_file_and_its_edit_page_open(): void
    {
        $student = $this->seedSomeActivity();

        $this->get(StudentResource::getUrl('view', ['record' => $student]))
            ->assertOk()
            ->assertSee($student->full_name);

        $this->get(StudentResource::getUrl('edit', ['record' => $student]))->assertOk();
    }

    public function test_the_course_page_opens_with_its_circles_students_sessions_and_transfers(): void
    {
        $student = $this->seedSomeActivity();
        $course = $student->enrollments()->firstOrFail()->course;

        $this->get(CourseResource::getUrl('edit', ['record' => $course]))
            ->assertOk()
            ->assertSee($course->name);
    }

    public function test_an_activity_opens_with_the_students_who_attended_it(): void
    {
        $this->seedSomeActivity();

        foreach (Activity::query()->get() as $activity) {
            $this->get(ActivityResource::getUrl('edit', ['record' => $activity]))
                ->assertOk()
                ->assertSee($activity->title);
        }
    }

    /**
     * A student with an enrollment, a transfer, an attendance, a recitation
     * that completes a juz (so an exam and points exist), an activity of
     * their circle and one of the whole mosque, an expense, and the
     * attendance of the circle's supervisor.
     */
    private function seedSomeActivity(): Student
    {
        $course = Course::factory()->create();
        $supervisor = Teacher::factory()->create();
        $circle = Circle::factory()->create(['course_id' => $course->getKey()]);
        $otherCircle = Circle::factory()->create(['course_id' => $course->getKey(), 'supervisor_teacher_id' => $supervisor->getKey()]);
        $student = Student::factory()->complete()->create();
        $enrollment = $this->enroll($student, $course, $circle);

        app(TransferStudent::class)->handle($enrollment, [
            'to_circle_id' => $otherCircle->getKey(),
            'transferred_on' => now()->subDay()->toDateString(),
        ]);

        $this->attend($enrollment);
        $this->recite($enrollment, 582, 604);
        $this->holdActivity([$enrollment], $otherCircle, ['title' => 'مسابقة الحلقة']);
        $this->holdActivity([$enrollment], overrides: ['title' => 'رحلة الجامع']);
        $this->spend();

        app(RecordManualTeacherAttendance::class)->handle([
            'teacher_id' => $supervisor->getKey(),
            'course_id' => $course->getKey(),
            'attended_on' => now()->toDateString(),
        ]);

        Student::factory()->archived()->create();
        Student::factory()->create();

        return $student;
    }
}
