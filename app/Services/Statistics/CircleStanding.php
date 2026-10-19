<?php

namespace App\Services\Statistics;

/**
 * What the students of a circle did together during the period of a report.
 */
final readonly class CircleStanding
{
    public function __construct(
        public int $circleId,
        public string $circleName,
        public int $courseId,
        public string $courseName,
        public ?string $supervisorName,
        public int $students,
        public int $newPages,
        public int $recitedPages,
        public int $points,
        public int $attended,
        public int $sessions,
        /** Juz passed in an exam, counted per student (see StudentStanding::$examsPassed). */
        public int $examsPassed,
    ) {}

    public function attendanceRate(): ?float
    {
        return Rates::attendance($this->attended, $this->sessions);
    }

    /**
     * Points per student. A total alone is unfair to small circles, so
     * the average is always shown next to it.
     */
    public function averagePoints(): ?float
    {
        return Rates::average($this->points, $this->students);
    }

    public function averageNewPages(): ?float
    {
        return Rates::average($this->newPages, $this->students);
    }
}
