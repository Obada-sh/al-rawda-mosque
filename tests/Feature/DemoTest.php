<?php

namespace Tests\Feature;

use App\Console\Commands\BuildDemo;
use App\Contracts\SettingsStore;
use App\Enums\CourseStatus;
use App\Enums\PointSource;
use App\Enums\RankingCriterion;
use App\Models\Activity;
use App\Models\Attendance;
use App\Models\Circle;
use App\Models\CircleTransfer;
use App\Models\Course;
use App\Models\CourseSession;
use App\Models\Enrollment;
use App\Models\Expense;
use App\Models\PointTransaction;
use App\Models\Recitation;
use App\Models\Student;
use App\Models\TeacherAttendance;
use App\Models\User;
use App\Services\Demo\DemoAccount;
use App\Services\Demo\DemoMode;
use App\Services\Statistics\CircleLeaders;
use App\Services\Statistics\QuranKnight;
use App\Services\Statistics\ReportFilter;
use App\Services\Statistics\StudentStandings;
use App\Support\Dates;
use App\Support\SettingKey;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * The invented data of the demo is recorded through the real actions:
 * building it must never break a rule of the system.
 */
class DemoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-10-07 17:00:00');
    }

    /**
     * The database of the tests plays a demo database.
     */
    private function thisIsTheDemo(): void
    {
        $this->app->instance(DemoMode::class, new DemoMode('rawda_demo', (int) config('rawda.demo.port')));
    }

    public function test_the_demo_is_built_by_the_rules_of_the_system(): void
    {
        // A handful of students instead of the full demo, over the same weeks.
        app(DemoSeeder::class)->run(8);

        $admin = User::query()->sole();
        $this->assertSame(DemoAccount::USERNAME, $admin->username);
        $this->assertSame(DemoAccount::NAME, $admin->name);
        $this->assertTrue(Hash::check(DemoAccount::PASSWORD, $admin->password));
        $this->assertSame(DemoSeeder::MOSQUE_NAME, app(SettingsStore::class)->get(SettingKey::MOSQUE_NAME));

        // A course that is over and a running one, with their circles.
        $this->assertSame(
            [CourseStatus::Finished, CourseStatus::Active],
            Course::query()->orderBy('start_date')->get()->map(fn (Course $course): CourseStatus => $course->status)->all(),
        );
        $autumn = Course::query()->active()->sole();
        $this->assertSame(4, Circle::query()->where('course_id', $autumn->getKey())->count());
        $this->assertSame(6, Circle::query()->count());

        // Eight students in the running course, four of whom were in the summer one; one more in the archive.
        $this->assertSame(9, Student::query()->count());
        $this->assertSame(1, Student::query()->whereNotNull('archived_at')->count());
        $this->assertSame(8, Enrollment::query()->where('course_id', $autumn->getKey())->count());
        $this->assertSame(12, Enrollment::query()->count());

        // Weeks of life in both courses.
        $this->assertGreaterThan(100, Attendance::query()->count());
        // The supervisors and assistants came on most days of class, not on all of them.
        $everyDay = Course::query()->get()->sum(fn (Course $course): int => $course->sessions()->count() * Circle::query()
            ->where('course_id', $course->getKey())
            ->get()
            ->flatMap(fn (Circle $circle): array => [$circle->supervisor_teacher_id, $circle->assistant_teacher_id])
            ->filter()
            ->unique()
            ->count());
        $teacherDays = TeacherAttendance::query()->count();
        $this->assertGreaterThan($everyDay * 0.7, $teacherDays);
        $this->assertLessThan($everyDay, $teacherDays);
        $this->assertGreaterThan(50, Recitation::query()->count());
        $this->assertGreaterThan(100, PointTransaction::query()->count());
        $this->assertGreaterThanOrEqual(48, Expense::query()->count());
        $this->assertSame(1, CircleTransfer::query()->count());
        $this->assertSame(1, Enrollment::query()->whereNotNull('left_on')->count());

        // A competition of a circle, a trip of the whole mosque, and a meeting without points.
        $this->assertSame(3, Activity::query()->count());
        $this->assertSame(1, Activity::query()->whereNull('circle_id')->count());
        $trip = Activity::query()->whereNull('circle_id')->sole();
        $this->assertSame(6, $trip->attendances()->count());
        $this->assertSame(6 * $trip->points, (int) PointTransaction::query()->where('source', PointSource::Activity)->where('reason', $trip->title)->sum('points'));

        // And every report reads it.
        $filter = new ReportFilter(courseId: $autumn->getKey());
        $this->assertCount(8, app(StudentStandings::class)->for($filter));
        $this->assertNotEmpty(app(QuranKnight::class)->ranking($filter));
        $this->assertNotEmpty(app(CircleLeaders::class)->of($filter, RankingCriterion::NewPages, 3));
    }

    public function test_a_demo_that_is_already_there_is_left_alone(): void
    {
        $this->thisIsTheDemo();
        User::factory()->create(['username' => 'someone']);

        $this->artisan('rawda:demo')
            ->expectsOutputToContain('already there')
            ->assertSuccessful();

        $this->assertSame(['someone'], User::query()->pluck('username')->all());
    }

    public function test_a_demo_left_alone_for_weeks_offers_to_be_built_again_and_takes_no_for_an_answer(): void
    {
        $this->thisIsTheDemo();
        app(DemoSeeder::class)->run(4);

        $lastClass = Dates::parse((string) CourseSession::query()->has('attendances')->max('session_date'));
        $recorded = [Student::query()->count(), Attendance::query()->count(), Recitation::query()->count()];

        // The same week: nothing is asked.
        $this->travelTo($lastClass->addDays(BuildDemo::STALE_AFTER_DAYS)->setTime(17, 0));

        $this->artisan('rawda:demo')
            ->expectsOutputToContain('already there')
            ->assertSuccessful();

        // Three weeks later its pages about "today" are empty: a new demo is offered.
        $this->travelTo($lastClass->addDays(21)->setTime(17, 0));

        $this->artisan('rawda:demo')
            ->expectsConfirmation(BuildDemo::staleQuestion(21), 'no')
            ->expectsOutputToContain('already there')
            ->assertSuccessful();

        $this->assertSame($recorded, [Student::query()->count(), Attendance::query()->count(), Recitation::query()->count()]);
    }

    public function test_the_demo_refuses_any_database_that_is_not_a_demo_database(): void
    {
        Student::factory()->create(['full_name' => 'طالب حقيقي']);

        $this->artisan('rawda:demo')
            ->expectsOutputToContain('refuses to touch the database')
            ->assertFailed();

        $this->artisan('rawda:demo', ['--fresh' => true])->assertFailed();

        $this->assertSame(['طالب حقيقي'], Student::query()->pluck('full_name')->all());
        $this->assertSame(0, User::query()->count());
    }
}
