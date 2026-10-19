<?php

namespace App\Services\Statistics;

use App\Enums\RankingCriterion;
use App\Models\Circle;
use App\Support\Dates;
use Illuminate\Support\Collection;
use InvalidArgumentException;

/**
 * The students of each circle in a period, best first: by new pages, by
 * points, by attendance or by the juz they passed an exam of. This is
 * what the slides of the leaders show.
 *
 * - A student counts for the circle they are in now, with everything
 *   they did in the period, also before being moved to it.
 * - Only students still in the course at the end of the period are
 *   listed.
 * - Asked for the first few (top), a circle lists only those who have
 *   something to show: at least one new page, more than zero points, one
 *   attended session, one juz passed. Asked for everyone, it lists all its
 *   students, those with nothing yet at the end.
 * - Students with the same score share a place. Between them, the one who
 *   did better on another measure is listed first; when the list is cut
 *   in the middle of a tie, that is who stays. Attendance is ranked by its
 *   rate, so a student who joined late is not behind for the sessions he
 *   was not in the course for; the score shown is the sessions attended.
 * - A circle in which nobody has anything to show is left out.
 */
final class CircleLeaders
{
    /** What the students of a circle can be ranked by. */
    public const CRITERIA = [RankingCriterion::NewPages, RankingCriterion::Points, RankingCriterion::Attendance, RankingCriterion::Exams];

    public function __construct(
        private readonly StudentStandings $students,
        private readonly StudentRanking $ranking,
    ) {}

    /**
     * @param  ReportFilter  $filter  the course, the period, and one circle or all of them
     * @param  int|null  $top  how many students to keep in each circle, at most; null for all of them
     * @return Collection<int, CircleLeaderboard> ordered by circle name
     */
    public function of(ReportFilter $filter, RankingCriterion $criterion, ?int $top = null): Collection
    {
        if ($top !== null && $top < 1) {
            throw new InvalidArgumentException('A leaderboard needs room for at least one student.');
        }

        $tieBreaker = self::tieBreakerOf($criterion);
        $lastDay = $filter->until ?? Dates::today()->toDateString();

        $byCircle = $this->students->for($filter)
            ->filter(fn (StudentStanding $standing): bool => $standing->wasEnrolledOn($lastDay))
            ->groupBy('circleId');

        return Circle::query()
            ->with(['supervisor', 'assistant'])
            ->whereKey($byCircle->keys()->all())
            ->orderBy('name')
            ->orderBy('id')
            ->get()
            ->map(function (Circle $circle) use ($byCircle, $criterion, $tieBreaker, $top): ?CircleLeaderboard {
                /** @var Collection<int, StudentStanding> $members ordered by name */
                $members = $byCircle->get($circle->getKey(), collect());

                // A ranking keeps the order it is given between equal scores: the other measure decides it first.
                $members = $this->ranking->rank($members, $tieBreaker)
                    ->map(fn (RankedStanding $ranked): StudentStanding => $ranked->standing);

                $students = $this->ranking->rank($members, $criterion)
                    ->map(fn (RankedStanding $ranked): CircleLeader => new CircleLeader(
                        place: $ranked->place,
                        studentId: $ranked->standing->studentId,
                        studentName: $ranked->standing->studentName,
                        score: self::scoreOf($ranked->standing, $criterion),
                    ));

                if ($students->every(fn (CircleLeader $student): bool => $student->score <= 0)) {
                    return null;
                }

                $listed = $top === null
                    ? $students
                    : $students->filter(fn (CircleLeader $student): bool => $student->score > 0)->take($top);

                return new CircleLeaderboard(
                    circleId: $circle->getKey(),
                    circleName: $circle->name,
                    supervisorName: $circle->supervisor?->full_name,
                    assistantName: $circle->assistant?->full_name,
                    leaders: $listed->values()->all(),
                );
            })
            ->filter()
            ->values();
    }

    /**
     * What the slide shows next to a student's name.
     */
    private static function scoreOf(StudentStanding $standing, RankingCriterion $criterion): int
    {
        return match ($criterion) {
            RankingCriterion::NewPages => $standing->newPages,
            RankingCriterion::Points => $standing->points,
            RankingCriterion::Attendance => $standing->attended,
            RankingCriterion::Exams => $standing->examsPassed,
        };
    }

    /**
     * The measure that orders students with the same score.
     */
    private static function tieBreakerOf(RankingCriterion $criterion): RankingCriterion
    {
        return match ($criterion) {
            RankingCriterion::NewPages, RankingCriterion::Attendance => RankingCriterion::Points,
            RankingCriterion::Points, RankingCriterion::Exams => RankingCriterion::NewPages,
        };
    }
}
