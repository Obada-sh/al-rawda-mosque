<?php

namespace App\Services\Statistics;

use Illuminate\Support\Collection;

/**
 * The totals of everything in the scope of a report: one circle, one
 * course, or the whole mosque.
 */
final readonly class ScopeTotals
{
    public function __construct(
        /** Different students (a student in two courses is counted once). */
        public int $students,
        public int $newPages,
        public int $recitedPages,
        public int $points,
        public int $attended,
        public int $sessions,
    ) {}

    /**
     * @param  Collection<int, StudentStanding>  $standings
     */
    public static function of(Collection $standings): self
    {
        return new self(
            students: $standings->unique('studentId')->count(),
            newPages: (int) $standings->sum('newPages'),
            recitedPages: (int) $standings->sum('recitedPages'),
            points: (int) $standings->sum('points'),
            attended: (int) $standings->sum('attended'),
            sessions: (int) $standings->sum('sessions'),
        );
    }

    public function attendanceRate(): ?float
    {
        return Rates::attendance($this->attended, $this->sessions);
    }

    public function averagePoints(): ?float
    {
        return Rates::average($this->points, $this->students);
    }
}
