<?php

namespace App\Services\Export;

use App\Enums\RankingCriterion;
use App\Services\Statistics\CircleLeaderboard;

/**
 * What the slides of the leaders show: the students of each circle, best
 * first, on one slide per circle (or more for a large circle).
 */
final readonly class LeadersDeck
{
    /**
     * @param  string|null  $from  first day of the period (Y-m-d), or null for "since the beginning"
     * @param  string|null  $until  last day of the period (Y-m-d), or null for "until today"
     * @param  RankingCriterion  $criterion  what the students were ranked by
     * @param  list<CircleLeaderboard>  $circles  in the order of the slides
     */
    public function __construct(
        public string $courseName,
        public ?string $from,
        public ?string $until,
        public RankingCriterion $criterion,
        public array $circles,
    ) {}
}
