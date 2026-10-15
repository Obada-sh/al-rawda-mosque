<?php

namespace App\Services\Statistics;

/**
 * A student's figures together with their place in a ranking.
 */
final readonly class RankedStanding
{
    /**
     * @param  int  $place  1 for the first; students with the same score share a place
     */
    public function __construct(
        public int $place,
        public StudentStanding $standing,
    ) {}
}
