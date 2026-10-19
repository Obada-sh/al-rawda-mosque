<?php

namespace App\Services\Statistics;

/**
 * The students of one circle in a period, best first.
 */
final readonly class CircleLeaderboard
{
    /**
     * @param  list<CircleLeader>  $leaders  the first place first; never empty
     */
    public function __construct(
        public int $circleId,
        public string $circleName,
        public ?string $supervisorName,
        public ?string $assistantName,
        public array $leaders,
    ) {}
}
