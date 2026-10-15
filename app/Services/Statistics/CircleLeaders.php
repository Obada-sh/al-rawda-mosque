<?php

namespace App\Services\Statistics;

use App\Enums\RankingCriterion;
use App\Models\Circle;
use App\Support\Dates;
use Illuminate\Support\Collection;
use InvalidArgumentException;

/**
 * The best students of each circle in a period, by new pages or by
 * points. This is what the slides of the leaders show.
 *
 * - A student counts for the circle they are in now, with everything
 *   they did in the period, also before being moved to it.
 * - Only students still in the course at the end of the period are
 *   listed, and only those who have something to show: at least one new
 *   page, or more than zero points.
 * - Students with the same score share a place. Between them, the one who
 *   did better on the other measure is listed first; when the list is cut
 *   in the middle of a tie, that is who stays.
 * - A circle nobody leads is left out.
 */
final class CircleLeaders
{
    /** What the students of a circle can be ranked by. */
    public const CRITERIA = [RankingCriterion::NewPages, RankingCriterion::Points];

    public function __construct(
        private readonly StudentStandings $students,
        private readonly StudentRanking $ranking,
    ) {}

    /**
     * @param  RankingCriterion  $criterion  one of CRITERIA
     * @param  int  $top  how many students to keep in each circle, at most
     * @return Collection<int, CircleLeaderboard> ordered by circle name
     */
    public function of(ReportFilter $filter, RankingCriterion $criterion, int $top): Collection
    {
        if ($top < 1) {
            throw new InvalidArgumentException('A leaderboard needs room for at least one student.');
        }

        $tieBreaker = match ($criterion) {
            RankingCriterion::NewPages => RankingCriterion::Points,
            RankingCriterion::Points => RankingCriterion::NewPages,
            default => throw new InvalidArgumentException('Circle leaders are ranked by new pages or by points.'),
        };

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

                $leaders = $this->ranking->rank($members, $criterion)
                    ->map(fn (RankedStanding $ranked): CircleLeader => new CircleLeader(
                        place: $ranked->place,
                        studentId: $ranked->standing->studentId,
                        studentName: $ranked->standing->studentName,
                        score: $criterion === RankingCriterion::NewPages ? $ranked->standing->newPages : $ranked->standing->points,
                    ))
                    ->filter(fn (CircleLeader $leader): bool => $leader->score > 0)
                    ->take($top)
                    ->values()
                    ->all();

                return $leaders === [] ? null : new CircleLeaderboard(
                    circleId: $circle->getKey(),
                    circleName: $circle->name,
                    supervisorName: $circle->supervisor?->full_name,
                    assistantName: $circle->assistant?->full_name,
                    leaders: $leaders,
                );
            })
            ->filter()
            ->values();
    }
}
