<?php

namespace Tests\Feature;

use App\Actions\Enrollments\UpdateEnrollmentDates;
use App\Actions\Exams\OpenJuzExam;
use App\Actions\Exams\RecordJuzExamResult;
use App\Enums\ExamGrade;
use App\Enums\RankingCriterion;
use App\Enums\SessionStatus;
use App\Models\Circle;
use App\Models\Course;
use App\Models\Student;
use App\Services\Statistics\CircleStanding;
use App\Services\Statistics\CircleStandings;
use App\Services\Statistics\CourseStandings;
use App\Services\Statistics\ExamSummaries;
use App\Services\Statistics\ExamSummary;
use App\Services\Statistics\KnightEntry;
use App\Services\Statistics\QuranKnight;
use App\Services\Statistics\RankedStanding;
use App\Services\Statistics\ReportFilter;
use App\Services\Statistics\ScopeTotals;
use App\Services\Statistics\StudentRanking;
use App\Services\Statistics\StudentStanding;
use App\Services\Statistics\StudentStandings;
use App\Services\Statistics\TeacherStanding;
use App\Services\Statistics\TeacherStandings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\BuildsDomain;
use Tests\Concerns\SeedsReportWeek;
use Tests\TestCase;

/**
 * The figures of the reports, on the week described in SeedsReportWeek.
 */
class StatisticsTest extends TestCase
{
    use BuildsDomain;
    use RefreshDatabase;
    use SeedsReportWeek;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-10-07 17:00:00');
        $this->seedReportWeek();
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function filter(array $input = []): ReportFilter
    {
        return ReportFilter::fromInput($input);
    }

    /**
     * @param  array<string, mixed>  $input
     * @return Collection<string, StudentStanding> keyed by student name
     */
    private function standings(array $input = []): Collection
    {
        return app(StudentStandings::class)->for($this->filter($input))->keyBy('studentName');
    }

    /**
     * @return array{0: int, 1: int, 2: int, 3: int, 4: int, 5: float|null} new pages, all pages, points, attended, sessions, rate
     */
    private function figures(StudentStanding $standing): array
    {
        return [
            $standing->newPages,
            $standing->recitedPages,
            $standing->points,
            $standing->attended,
            $standing->sessions,
            $standing->attendanceRate(),
        ];
    }

    public function test_each_student_gets_their_pages_points_and_attendance(): void
    {
        $standings = $this->standings();

        $this->assertSame(['أحمد', 'بلال', 'خالد'], $standings->keys()->all());
        $this->assertSame([12, 18, 27, 3, 3, 100.0], $this->figures($standings['أحمد']));
        $this->assertSame([4, 4, 6, 1, 3, 33.3], $this->figures($standings['بلال']));
        $this->assertSame([0, 0, 9, 2, 3, 66.7], $this->figures($standings['خالد']));

        $this->assertSame('حلقة أ', $standings['أحمد']->circleName);
        $this->assertSame('دورة الخريف', $standings['أحمد']->courseName);
        $this->assertFalse($standings['أحمد']->hasLeft());
    }

    public function test_a_period_counts_only_what_happened_inside_it(): void
    {
        $standings = $this->standings(['from' => '2026-10-02', 'until' => '2026-10-04']);

        $this->assertSame([2, 8, 5, 1, 1, 100.0], $this->figures($standings['أحمد']));
        $this->assertSame([0, 0, 0, 0, 1, 0.0], $this->figures($standings['بلال']));
        $this->assertSame([0, 0, 1, 1, 1, 100.0], $this->figures($standings['خالد']));
    }

    public function test_the_first_and_the_last_day_of_the_period_are_included(): void
    {
        $standings = $this->standings(['from' => '2026-10-01', 'until' => '2026-10-01']);

        $this->assertSame([10, 10, 21, 1, 1, 100.0], $this->figures($standings['أحمد']));
        $this->assertSame([0, 0, 1, 1, 1, 100.0], $this->figures($standings['بلال']));
        $this->assertSame([0, 0, 0, 0, 1, 0.0], $this->figures($standings['خالد']));
    }

    public function test_a_report_is_narrowed_to_a_circle_or_to_a_course(): void
    {
        $other = $this->enroll(Student::factory()->create(['full_name' => 'سمير']));

        $this->assertSame(['خالد'], $this->standings(['circle_id' => $this->circleB->getKey()])->keys()->all());
        $this->assertSame(['أحمد', 'بلال', 'خالد'], $this->standings(['course_id' => $this->course->getKey()])->keys()->all());
        $this->assertSame(['سمير'], $this->standings(['course_id' => $other->course_id])->keys()->all());
        $this->assertSame(['أحمد', 'بلال', 'خالد', 'سمير'], $this->standings()->keys()->all());
    }

    public function test_only_the_sessions_held_while_the_student_was_in_the_course_count(): void
    {
        $late = $this->enroll(Student::factory()->create(['full_name' => 'داود']), $this->course, $this->circleB, '2026-10-04');
        $leaver = $this->enroll(Student::factory()->create(['full_name' => 'سامي']), $this->course, $this->circleB);
        $this->attend($leaver, '2026-10-01');
        app(UpdateEnrollmentDates::class)->handle($leaver, [
            'enrolled_on' => $leaver->enrolled_on->toDateString(),
            'left_on' => '2026-10-02',
        ]);

        $standings = $this->standings();

        // Enrolled on the 4th: only the session of the 5th could be attended.
        $this->assertSame([0, 1, 0.0], [$standings['داود']->attended, $standings['داود']->sessions, $standings['داود']->attendanceRate()]);
        // Left on the 2nd: only the session of the 1st counts, and it was attended.
        $this->assertSame([1, 1, 100.0], [$standings['سامي']->attended, $standings['سامي']->sessions, $standings['سامي']->attendanceRate()]);
        $this->assertTrue($standings['سامي']->hasLeft());
        $this->assertSame($late->getKey(), $standings['داود']->enrollmentId);

        // A student who left before the period, or joined after it, is not in its report.
        $this->assertFalse($this->standings(['from' => '2026-10-03'])->has('سامي'));
        $this->assertFalse($this->standings(['until' => '2026-10-03'])->has('داود'));
        $this->assertTrue($this->standings(['until' => '2026-10-04'])->has('داود'));
    }

    public function test_a_student_without_a_held_session_has_no_attendance_rate(): void
    {
        $this->enroll(Student::factory()->create(['full_name' => 'سمير']));

        $standing = $this->standings()['سمير'];

        $this->assertSame(0, $standing->sessions);
        $this->assertNull($standing->attendanceRate());
    }

    public function test_a_cancelled_session_does_not_count(): void
    {
        $this->course->sessions()->create(['session_date' => '2026-10-06', 'status' => SessionStatus::Cancelled]);

        $this->assertSame(3, $this->standings()['أحمد']->sessions);
    }

    public function test_a_circle_is_the_sum_of_its_current_students(): void
    {
        $circles = app(CircleStandings::class)->for($this->filter(['course_id' => $this->course->getKey()]))->keyBy('circleName');

        $this->assertSame(['حلقة أ', 'حلقة ب'], $circles->keys()->all());

        /** @var CircleStanding $first */
        $first = $circles['حلقة أ'];
        $this->assertSame([2, 16, 22, 33, 4, 6], [$first->students, $first->newPages, $first->recitedPages, $first->points, $first->attended, $first->sessions]);
        $this->assertSame([66.7, 16.5, 8.0], [$first->attendanceRate(), $first->averagePoints(), $first->averageNewPages()]);

        /** @var CircleStanding $second */
        $second = $circles['حلقة ب'];
        $this->assertSame([1, 0, 9, 66.7, 9.0], [$second->students, $second->newPages, $second->points, $second->attendanceRate(), $second->averagePoints()]);
    }

    public function test_a_moved_student_takes_everything_to_the_new_circle(): void
    {
        $this->moveToCircle($this->ahmad, $this->circleB, '2026-10-06');

        $filter = $this->filter(['course_id' => $this->course->getKey()]);
        $circles = app(CircleStandings::class)->for($filter)->keyBy('circleName');

        $this->assertSame([1, 4, 6], [$circles['حلقة أ']->students, $circles['حلقة أ']->newPages, $circles['حلقة أ']->points]);
        $this->assertSame([2, 12, 36], [$circles['حلقة ب']->students, $circles['حلقة ب']->newPages, $circles['حلقة ب']->points]);

        // Nothing is lost and nothing is counted twice.
        $totals = ScopeTotals::of(app(StudentStandings::class)->for($filter));
        $this->assertSame([3, 16, 42], [$totals->students, $totals->newPages, $totals->points]);

        // Even a period before the move is counted for the circle the student is in now.
        $before = app(CircleStandings::class)
            ->for($this->filter(['course_id' => $this->course->getKey(), 'until' => '2026-10-05']))
            ->keyBy('circleName');
        $this->assertSame(12, $before['حلقة ب']->newPages);
    }

    public function test_a_circle_without_students_is_listed_for_its_course_with_no_figures(): void
    {
        Circle::factory()->create(['course_id' => $this->course->getKey(), 'name' => 'حلقة ج']);

        $empty = app(CircleStandings::class)->for($this->filter(['course_id' => $this->course->getKey()]))->keyBy('circleName')['حلقة ج'];

        $this->assertSame(0, $empty->students);
        $this->assertNull($empty->attendanceRate());
        $this->assertNull($empty->averagePoints());

        // Without a course, only the circles that have students are listed.
        $this->assertSame(
            ['حلقة أ', 'حلقة ب'],
            app(CircleStandings::class)->for($this->filter())->pluck('circleName')->all(),
        );
    }

    public function test_the_supervisor_of_a_circle_is_shown_with_it(): void
    {
        $this->circleA->update(['supervisor_teacher_id' => $this->yaser->getKey()]);

        $circles = app(CircleStandings::class)->for($this->filter(['course_id' => $this->course->getKey()]))->keyBy('circleName');

        $this->assertSame('الشيخ ياسر', $circles['حلقة أ']->supervisorName);
        $this->assertNull($circles['حلقة ب']->supervisorName);
    }

    public function test_courses_and_the_whole_mosque_are_totalled(): void
    {
        $other = $this->enroll(Student::factory()->create(['full_name' => 'سمير']), Course::factory()->create(['name' => 'دورة الصيف', 'points_per_page' => 5]));
        $alsoBilal = $this->enroll($this->bilal->student, $other->course, $other->circle);
        $this->recite($other, 100, 104, '2026-10-02');
        $this->recite($alsoBilal, 200, 200, '2026-10-02');

        $courses = app(CourseStandings::class)->for($this->filter())->keyBy('courseName');

        $this->assertSame([3, 16, 42, 66.7, 14.0], [
            $courses['دورة الخريف']->students,
            $courses['دورة الخريف']->newPages,
            $courses['دورة الخريف']->points,
            $courses['دورة الخريف']->attendanceRate(),
            $courses['دورة الخريف']->averagePoints(),
        ]);
        $this->assertSame([2, 6, 30], [$courses['دورة الصيف']->students, $courses['دورة الصيف']->newPages, $courses['دورة الصيف']->points]);
        $this->assertNull($courses['دورة الصيف']->attendanceRate());
        $this->assertTrue($courses['دورة الصيف']->isActive);

        // بلال is in both courses and is one student of the mosque.
        $mosque = ScopeTotals::of(app(StudentStandings::class)->for($this->filter()));
        $this->assertSame([4, 22, 28, 72, 6, 9], [$mosque->students, $mosque->newPages, $mosque->recitedPages, $mosque->points, $mosque->attended, $mosque->sessions]);
        $this->assertSame([66.7, 18.0], [$mosque->attendanceRate(), $mosque->averagePoints()]);
    }

    public function test_students_are_ranked_by_points_pages_or_attendance(): void
    {
        $standings = app(StudentStandings::class)->for($this->filter());
        $ranking = app(StudentRanking::class);

        $order = fn (RankingCriterion $criterion): array => $ranking->rank($standings, $criterion)
            ->map(fn (RankedStanding $ranked): string => "{$ranked->place} {$ranked->standing->studentName}")
            ->all();

        $this->assertSame(['1 أحمد', '2 خالد', '3 بلال'], $order(RankingCriterion::Points));
        $this->assertSame(['1 أحمد', '2 بلال', '3 خالد'], $order(RankingCriterion::NewPages));
        $this->assertSame(['1 أحمد', '2 خالد', '3 بلال'], $order(RankingCriterion::Attendance));
    }

    public function test_equal_students_share_a_place_and_a_student_without_a_rate_is_last(): void
    {
        $this->award($this->bilal, 3, '2026-10-06');
        $this->enroll(Student::factory()->create(['full_name' => 'سمير']));

        $standings = app(StudentStandings::class)->for($this->filter());
        $ranking = app(StudentRanking::class);

        // بلال now has 9 points like خالد; سمير has none.
        $this->assertSame(
            ['1 أحمد', '2 بلال', '2 خالد', '4 سمير'],
            $ranking->rank($standings, RankingCriterion::Points)
                ->map(fn (RankedStanding $ranked): string => "{$ranked->place} {$ranked->standing->studentName}")
                ->all(),
        );

        $this->assertSame('سمير', $ranking->rank($standings, RankingCriterion::Attendance)->last()->standing->studentName);
    }

    public function test_teachers_are_counted_by_the_pages_they_heard(): void
    {
        $teachers = app(TeacherStandings::class)->for($this->filter());

        $this->assertSame(['الشيخ ياسر', 'الشيخ عمر'], $teachers->pluck('teacherName')->all());

        /** @var TeacherStanding $yaser */
        $yaser = $teachers->first();
        $this->assertSame([2, 18, 12, 1], [$yaser->recitations, $yaser->pages, $yaser->newPages, $yaser->students]);

        /** @var TeacherStanding $omar */
        $omar = $teachers->last();
        $this->assertSame([1, 4, 4, 1], [$omar->recitations, $omar->pages, $omar->newPages, $omar->students]);

        $inPeriod = app(TeacherStandings::class)->for($this->filter(['from' => '2026-10-02', 'until' => '2026-10-04']));
        $this->assertSame([['الشيخ ياسر', 8, 2]], $inPeriod->map(fn (TeacherStanding $teacher): array => [$teacher->teacherName, $teacher->pages, $teacher->newPages])->all());

        $inCircle = app(TeacherStandings::class)->for($this->filter(['circle_id' => $this->circleB->getKey()]));
        $this->assertTrue($inCircle->isEmpty());
    }

    public function test_the_quran_knight_ranks_by_new_pages_and_ties_share_a_place(): void
    {
        $summer = Course::factory()->create(['name' => 'دورة الصيف']);
        $circle = Circle::factory()->create(['course_id' => $summer->getKey(), 'name' => 'حلقة النور']);
        $samir = $this->enroll(Student::factory()->create(['full_name' => 'سمير']), $summer, $circle);
        $alsoBilal = $this->enroll($this->bilal->student, $summer, $circle);
        $this->recite($samir, 100, 111, '2026-10-02');
        $this->recite($alsoBilal, 200, 202, '2026-10-04');

        $knight = app(QuranKnight::class);
        $lines = fn (array $input): array => $knight->ranking($this->filter($input))
            ->map(fn (KnightEntry $entry): string => "{$entry->place} {$entry->studentName} {$entry->newPages}")
            ->all();

        // The whole mosque: أحمد and سمير are tied; بلال's two courses are added up; خالد has no new page.
        $this->assertSame(['1 أحمد 12', '1 سمير 12', '3 بلال 7'], $lines([]));

        // One course.
        $this->assertSame(['1 أحمد 12', '2 بلال 4'], $lines(['course_id' => $this->course->getKey()]));

        // A period.
        $this->assertSame(['1 بلال 7'], $lines(['from' => '2026-10-04', 'until' => '2026-10-05']));

        // One circle.
        $this->assertSame(['1 سمير 12', '2 بلال 3'], $lines(['circle_id' => $circle->getKey()]));

        $bilal = $knight->ranking($this->filter())->firstWhere('studentName', 'بلال');
        $this->assertSame(['حلقة أ (دورة الخريف)', 'حلقة النور (دورة الصيف)'], $bilal->circles);
    }

    public function test_exams_are_summarised_per_circle(): void
    {
        $this->recite($this->khalid, 582, 604, '2026-10-05', $this->omar);

        $taken = app(OpenJuzExam::class)->handle([
            'student_id' => $this->ahmad->student_id,
            'juz_no' => 1,
            'enrollment_id' => $this->ahmad->getKey(),
        ]);
        app(RecordJuzExamResult::class)->handle($taken, [
            'exam_date' => '2026-10-06',
            'examiner_teacher_id' => $this->yaser->getKey(),
            'grade' => ExamGrade::Excellent->value,
        ]);

        app(OpenJuzExam::class)->handle([
            'student_id' => Student::factory()->create()->getKey(),
            'juz_no' => 2,
        ]);

        $lines = fn (array $input): array => app(ExamSummaries::class)->for($this->filter($input))
            ->map(fn (ExamSummary $summary): array => [$summary->circleName, $summary->pending, $summary->done, $summary->grades])
            ->all();

        $this->assertSame([
            ['حلقة أ', 0, 1, ['excellent' => 1]],
            ['حلقة ب', 1, 0, []],
            [null, 1, 0, []],
        ], $lines([]));

        // Narrowed to the course, the exam that belongs to no course is left out.
        $this->assertSame([
            ['حلقة أ', 0, 1, ['excellent' => 1]],
            ['حلقة ب', 1, 0, []],
        ], $lines(['course_id' => $this->course->getKey()]));

        // Taken exams are counted by their date; waiting ones always.
        $this->assertSame([
            ['حلقة ب', 1, 0, []],
        ], $lines(['course_id' => $this->course->getKey(), 'until' => '2026-10-05']));

        $this->assertSame(1, app(ExamSummaries::class)->for($this->filter())->first()->countOf(ExamGrade::Excellent));
        $this->assertSame(0, app(ExamSummaries::class)->for($this->filter())->first()->countOf(ExamGrade::Retake));
    }

    public function test_each_student_counts_the_juz_passed_in_the_period(): void
    {
        $this->examine($this->ahmad, 30, '2026-10-02', ExamGrade::Excellent);
        // The same juz again, for a better grade: still one juz.
        $this->examine($this->ahmad, 30, '2026-10-04', ExamGrade::Excellent);
        $this->examine($this->ahmad, 29, '2026-10-03', ExamGrade::Retake);
        $this->examine($this->ahmad, 28);
        $this->examine($this->bilal, 30, '2026-10-05', ExamGrade::Acceptable);

        // An exam of خالد that belongs to no course counts in none.
        $unattached = app(OpenJuzExam::class)->handle(['student_id' => $this->khalid->student_id, 'juz_no' => 30]);
        app(RecordJuzExamResult::class)->handle($unattached, [
            'exam_date' => '2026-10-05',
            'examiner_teacher_id' => $this->yaser->getKey(),
            'grade' => ExamGrade::Good->value,
        ]);

        $passed = fn (array $input = []): array => $this->standings($input)
            ->map(fn (StudentStanding $standing): int => $standing->examsPassed)
            ->all();

        $this->assertSame(['أحمد' => 1, 'بلال' => 1, 'خالد' => 0], $passed());
        $this->assertSame(['أحمد' => 1, 'بلال' => 0, 'خالد' => 0], $passed(['until' => '2026-10-04']));
        $this->assertSame(['أحمد' => 0, 'بلال' => 1, 'خالد' => 0], $passed(['from' => '2026-10-05']));

        // Circles, courses and the whole mosque add them up.
        $circles = app(CircleStandings::class)->for($this->filter(['course_id' => $this->course->getKey()]))->keyBy('circleName');
        $this->assertSame([2, 0], [$circles['حلقة أ']->examsPassed, $circles['حلقة ب']->examsPassed]);
        $this->assertSame(2, app(CourseStandings::class)->for($this->filter())->sole()->examsPassed);
        $this->assertSame(2, ScopeTotals::of(app(StudentStandings::class)->for($this->filter()))->examsPassed);

        // Ranked by exams, the two who passed a juz share the first place.
        $this->assertSame(
            ['1 أحمد', '1 بلال', '3 خالد'],
            app(StudentRanking::class)->rank(app(StudentStandings::class)->for($this->filter()), RankingCriterion::Exams)
                ->map(fn (RankedStanding $ranked): string => "{$ranked->place} {$ranked->standing->studentName}")
                ->all(),
        );
    }

    public function test_the_filter_accepts_only_a_period_that_ends_after_it_starts(): void
    {
        $filter = ReportFilter::fromInput(['from' => '2026-10-01 00:00:00', 'until' => '2026-10-05 00:00:00', 'course_id' => (string) $this->course->getKey()]);

        $this->assertSame(['2026-10-01', '2026-10-05', $this->course->getKey(), null], [$filter->from, $filter->until, $filter->courseId, $filter->circleId]);
        $this->assertTrue($filter->hasPeriod());
        $this->assertFalse(ReportFilter::fromInput([])->hasPeriod());

        $this->assertNull(ReportFilter::tryFromInput(['from' => '2026-10-05', 'until' => '2026-10-01']));
        $this->assertNull(ReportFilter::tryFromInput(['course_id' => 999999]));
        $this->assertNull(ReportFilter::tryFromInput(['from' => 'not a date']));

        $this->expectException(ValidationException::class);
        ReportFilter::fromInput(['from' => '2026-10-05', 'until' => '2026-10-01']);
    }
}
