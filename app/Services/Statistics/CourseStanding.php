<?php

namespace App\Services\Statistics;

/**
 * What the students of a course did together during the period of a report.
 */
final readonly class CourseStanding
{
    public function __construct(
        public int $courseId,
        public string $courseName,
        public bool $isActive,
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

    public function averagePoints(): ?float
    {
        return Rates::average($this->points, $this->students);
    }
}
