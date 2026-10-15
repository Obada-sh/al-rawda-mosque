<?php

namespace App\Services\Statistics;

use App\Enums\RankingCriterion;
use App\Support\Ranking;
use Illuminate\Support\Collection;

/**
 * Orders students by points, by new pages or by attendance, and gives
 * each one a place. Students with the same score share a place.
 */
final class StudentRanking
{
    /**
     * @param  Collection<int, StudentStanding>  $standings  ordered by student name
     * @return Collection<int, RankedStanding> the first place first; equal scores stay in name order
     */
    public function rank(Collection $standings, RankingCriterion $criterion): Collection
    {
        $ordered = $standings
            ->sortByDesc(fn (StudentStanding $standing): float => $this->score($standing, $criterion))
            ->values();

        $places = Ranking::places($ordered, fn (StudentStanding $standing): float => $this->score($standing, $criterion));

        return $ordered->map(fn (StudentStanding $standing, int $index): RankedStanding => new RankedStanding(
            place: $places[$index],
            standing: $standing,
        ));
    }

    /**
     * A student with no held session has no attendance rate; they are
     * placed after everyone who has one.
     */
    private function score(StudentStanding $standing, RankingCriterion $criterion): float
    {
        return match ($criterion) {
            RankingCriterion::Points => (float) $standing->points,
            RankingCriterion::NewPages => (float) $standing->newPages,
            RankingCriterion::Attendance => $standing->attendanceRate() ?? -1.0,
        };
    }
}
