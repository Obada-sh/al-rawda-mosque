<?php

namespace Tests\Feature;

use App\Actions\Enrollments\UpdateEnrollmentDates;
use App\Enums\ExamGrade;
use App\Enums\RankingCriterion;
use App\Models\Enrollment;
use App\Models\Student;
use App\Services\Statistics\CircleLeader;
use App\Services\Statistics\CircleLeaderboard;
use App\Services\Statistics\CircleLeaders;
use App\Services\Statistics\ReportFilter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\Concerns\BuildsDomain;
use Tests\Concerns\SeedsReportWeek;
use Tests\TestCase;

/**
 * The students of each circle, best first, on the week described in
 * SeedsReportWeek: أحمد 12 new pages, 27 points and 3 sessions of 3, بلال
 * 4 pages, 6 points and 1 session of 3 (both in حلقة أ), خالد no page, 9
 * points and 2 sessions of 3 (حلقة ب).
 */
class CircleLeadersTest extends TestCase
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
     * The leaders as plain rows: circle => [[place, student, score], ...].
     *
     * @param  array<string, mixed>  $period
     * @return array<string, list<array{int, string, int}>>
     */
    private function leaders(RankingCriterion $criterion, ?int $top = 3, array $period = []): array
    {
        $filter = ReportFilter::fromInput(['course_id' => $this->course->getKey()] + $period);

        return app(CircleLeaders::class)->of($filter, $criterion, $top)
            ->mapWithKeys(fn (CircleLeaderboard $circle): array => [
                $circle->circleName => array_map(
                    fn (CircleLeader $leader): array => [$leader->place, $leader->studentName, $leader->score],
                    $circle->leaders,
                ),
            ])
            ->all();
    }

    private function join(string $name, string $circle = 'circleB'): Enrollment
    {
        return $this->enroll(Student::factory()->create(['full_name' => $name]), $this->course, $this->{$circle});
    }

    public function test_each_circle_lists_its_best_students_by_new_pages(): void
    {
        // خالد recited no new page: his circle has nobody to show and is left out.
        $this->assertSame([
            'حلقة أ' => [[1, 'أحمد', 12], [2, 'بلال', 4]],
        ], $this->leaders(RankingCriterion::NewPages));
    }

    public function test_each_circle_lists_its_best_students_by_points(): void
    {
        $this->assertSame([
            'حلقة أ' => [[1, 'أحمد', 27], [2, 'بلال', 6]],
            'حلقة ب' => [[1, 'خالد', 9]],
        ], $this->leaders(RankingCriterion::Points));
    }

    public function test_no_more_than_the_asked_number_is_listed(): void
    {
        $this->assertSame([
            'حلقة أ' => [[1, 'أحمد', 27]],
            'حلقة ب' => [[1, 'خالد', 9]],
        ], $this->leaders(RankingCriterion::Points, top: 1));
    }

    public function test_only_what_was_done_in_the_period_counts(): void
    {
        // From the 4th on, أحمد recited nothing new; بلال recited his four pages on the 5th.
        $this->assertSame([
            'حلقة أ' => [[1, 'بلال', 4]],
        ], $this->leaders(RankingCriterion::NewPages, period: ['from' => '2026-10-04']));

        // Until the 3rd: أحمد has 12 pages (24 points) and two attendances; خالد one attendance.
        $this->assertSame([
            'حلقة أ' => [[1, 'أحمد', 26], [2, 'بلال', 1]],
            'حلقة ب' => [[1, 'خالد', 1]],
        ], $this->leaders(RankingCriterion::Points, period: ['until' => '2026-10-03']));
    }

    public function test_a_student_without_a_positive_score_is_never_a_leader(): void
    {
        // بلال ends the 6th with 6 points; ten more points off leave him below zero.
        $this->award($this->bilal, -10, '2026-10-07');

        $this->assertSame([[1, 'أحمد', 27]], $this->leaders(RankingCriterion::Points)['حلقة أ']);
    }

    public function test_students_with_the_same_score_share_a_place(): void
    {
        $dawud = $this->join('داود');
        $sami = $this->join('سامي');
        $this->recite($dawud, 1, 5, '2026-10-05');
        $this->recite($sami, 1, 5, '2026-10-05');

        // The same pages and the same points: both are first, in the order of their names.
        $this->assertSame([[1, 'داود', 5], [1, 'سامي', 5]], $this->leaders(RankingCriterion::NewPages)['حلقة ب']);

        // By points they are tied too, and خالد comes after them as the third.
        $this->assertSame([[1, 'داود', 10], [1, 'سامي', 10], [3, 'خالد', 9]], $this->leaders(RankingCriterion::Points)['حلقة ب']);
    }

    public function test_between_tied_students_the_other_measure_decides_who_is_listed_first(): void
    {
        $dawud = $this->join('داود');
        $sami = $this->join('سامي');
        $this->recite($dawud, 1, 5, '2026-10-05');
        $this->recite($sami, 1, 5, '2026-10-05');
        $this->award($sami, 3, '2026-10-05');

        // The same pages, but سامي has more points: he is listed first, and still shares the place.
        $this->assertSame([[1, 'سامي', 5], [1, 'داود', 5]], $this->leaders(RankingCriterion::NewPages)['حلقة ب']);

        // When there is room for one only, he is the one who stays.
        $this->assertSame([[1, 'سامي', 5]], $this->leaders(RankingCriterion::NewPages, top: 1)['حلقة ب']);
    }

    public function test_a_moved_student_counts_for_the_new_circle_with_everything_they_did(): void
    {
        $this->moveToCircle($this->bilal, $this->circleB, '2026-10-06');

        $this->assertSame([
            'حلقة أ' => [[1, 'أحمد', 12]],
            'حلقة ب' => [[1, 'بلال', 4]],
        ], $this->leaders(RankingCriterion::NewPages));
    }

    public function test_a_student_who_left_before_the_end_of_the_period_is_not_listed(): void
    {
        app(UpdateEnrollmentDates::class)->handle($this->ahmad, [
            'enrolled_on' => $this->ahmad->enrolled_on->toDateString(),
            'left_on' => '2026-10-06',
        ]);

        // Today (the 7th) he is no longer in the course.
        $this->assertSame([[1, 'بلال', 4]], $this->leaders(RankingCriterion::NewPages)['حلقة أ']);

        // In a period that ends while he was still in it, he keeps his place.
        $this->assertSame(
            [[1, 'أحمد', 12], [2, 'بلال', 4]],
            $this->leaders(RankingCriterion::NewPages, period: ['until' => '2026-10-06'])['حلقة أ'],
        );
    }

    public function test_the_supervisor_and_the_assistant_of_the_circle_are_given(): void
    {
        $this->circleA->update([
            'supervisor_teacher_id' => $this->yaser->getKey(),
            'assistant_teacher_id' => $this->omar->getKey(),
        ]);

        $circles = app(CircleLeaders::class)
            ->of(new ReportFilter(courseId: $this->course->getKey()), RankingCriterion::Points, 3)
            ->keyBy('circleName');

        $this->assertSame(['الشيخ ياسر', 'الشيخ عمر'], [$circles['حلقة أ']->supervisorName, $circles['حلقة أ']->assistantName]);
        $this->assertSame([null, null], [$circles['حلقة ب']->supervisorName, $circles['حلقة ب']->assistantName]);
        $this->assertSame($this->circleA->getKey(), $circles['حلقة أ']->circleId);
        $this->assertSame($this->ahmad->student_id, $circles['حلقة أ']->leaders[0]->studentId);
    }

    public function test_the_circles_of_another_course_are_not_mixed_in(): void
    {
        $other = $this->enroll();
        $this->recite($other, 1, 30, '2026-10-05');

        $this->assertSame(['حلقة أ'], array_keys($this->leaders(RankingCriterion::NewPages)));
    }

    public function test_without_a_number_every_student_of_the_circle_is_listed(): void
    {
        $this->join('سامي', 'circleA');

        // سامي did nothing yet: he is listed last. خالد recited no page, but his circle has nobody to show.
        $this->assertSame([
            'حلقة أ' => [[1, 'أحمد', 12], [2, 'بلال', 4], [3, 'سامي', 0]],
        ], $this->leaders(RankingCriterion::NewPages, top: null));

        // A student below zero is listed too, after those with nothing.
        $this->award($this->bilal, -10, '2026-10-07');
        $this->assertSame(
            [[1, 'أحمد', 27], [2, 'سامي', 0], [3, 'بلال', -4]],
            $this->leaders(RankingCriterion::Points, top: null)['حلقة أ'],
        );
    }

    public function test_one_circle_can_be_asked_for_alone(): void
    {
        $filter = ReportFilter::fromInput(['course_id' => $this->course->getKey(), 'circle_id' => $this->circleB->getKey()]);

        $circles = app(CircleLeaders::class)->of($filter, RankingCriterion::Points);

        $this->assertSame(['حلقة ب'], $circles->pluck('circleName')->all());
    }

    public function test_attendance_is_ranked_by_its_rate_and_shows_the_sessions_attended(): void
    {
        // سامي joined on the 4th and came to the only session held since.
        $sami = $this->enroll(Student::factory()->create(['full_name' => 'سامي']), $this->course, $this->circleA, '2026-10-04');
        $this->attend($sami, '2026-10-05');

        // أحمد and سامي came to all their sessions and share the first place; أحمد has more points.
        $this->assertSame([
            'حلقة أ' => [[1, 'أحمد', 3], [1, 'سامي', 1], [3, 'بلال', 1]],
            'حلقة ب' => [[1, 'خالد', 2]],
        ], $this->leaders(RankingCriterion::Attendance, top: null));
    }

    public function test_exams_count_the_juz_passed_in_the_period(): void
    {
        $this->examine($this->ahmad, 30, '2026-10-02', ExamGrade::Excellent);
        $this->examine($this->ahmad, 29, '2026-10-03', ExamGrade::Retake);
        $this->examine($this->khalid, 30, '2026-10-04');
        $this->examine($this->khalid, 29);

        // A retake is not a pass, nor is an exam still waiting; بلال passed nothing.
        $this->assertSame([
            'حلقة أ' => [[1, 'أحمد', 1]],
            'حلقة ب' => [[1, 'خالد', 1]],
        ], $this->leaders(RankingCriterion::Exams));

        $this->assertSame([], $this->leaders(RankingCriterion::Exams, period: ['from' => '2026-10-05']));
    }

    public function test_a_leaderboard_needs_room_for_at_least_one_student(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->leaders(RankingCriterion::Points, top: 0);
    }
}
