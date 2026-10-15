<?php

namespace App\Services\Statistics;

/**
 * The best students of one circle in a period.
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
