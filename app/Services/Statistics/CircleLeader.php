<?php

namespace App\Services\Statistics;

/**
 * One of the best students of a circle.
 */
final readonly class CircleLeader
{
    /**
     * @param  int  $place  1 for the first; students with the same score share a place
     * @param  int  $score  their new pages or their points, whichever the circle was ranked by
     */
    public function __construct(
        public int $place,
        public int $studentId,
        public string $studentName,
        public int $score,
    ) {}
}
