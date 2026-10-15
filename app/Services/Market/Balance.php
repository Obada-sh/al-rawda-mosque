<?php

namespace App\Services\Market;

/**
 * A student's points in one course, as the market sees them: what they
 * earned (the points ledger, which the rankings count), what they spent at
 * the course's markets, and what is left to spend.
 */
final readonly class Balance
{
    public function __construct(
        public int $earned,
        public int $spent,
    ) {}

    /**
     * What the student can still spend. It can be below zero when points
     * were taken back after a purchase (a corrected recitation, a penalty).
     */
    public function available(): int
    {
        return $this->earned - $this->spent;
    }

    public function covers(int $points): bool
    {
        return $this->available() >= $points;
    }
}
