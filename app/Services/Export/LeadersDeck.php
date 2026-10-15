<?php

namespace App\Services\Export;

use App\Enums\RankingCriterion;
use App\Services\Statistics\CircleLeaderboard;

/**
 * What the slides of the leaders show: one slide per circle.
 */
final readonly class LeadersDeck
{
    /**
     * @param  string|null  $from  first day of the period (Y-m-d), or null for "since the beginning"
     * @param  string|null  $until  last day of the period (Y-m-d), or null for "until today"
     * @param  RankingCriterion  $criterion  what the students were ranked by: new pages or points
     * @param  int  $top  how many leaders a slide has room for
     * @param  list<CircleLeaderboard>  $circles  in the order of the slides
     */
    public function __construct(
        public string $courseName,
        public ?string $from,
        public ?string $until,
        public RankingCriterion $criterion,
        public int $top,
        public array $circles,
    ) {}
}
