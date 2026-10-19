<?php

namespace App\Services\Statistics;

/**
 * A student of a circle, in the order of a ranking.
 */
final readonly class CircleLeader
{
    /**
     * @param  int  $place  1 for the first; students with the same score share a place
     * @param  int  $score  what the circle was ranked by: new pages, points (which can be below zero),
     *                      sessions attended, or juz passed in an exam
     */
    public function __construct(
        public int $place,
        public int $studentId,
        public string $studentName,
        public int $score,
    ) {}
}
