<?php

namespace App\Services\Statistics;

use App\Enums\ExamGrade;

/**
 * The juz exams of the students of one circle: how many are waiting, and
 * how the exams that were taken in the period went.
 */
final readonly class ExamSummary
{
    /**
     * @param  int|null  $circleId  null for exams that are not tied to a course
     * @param  array<string, int>  $grades  grade value => number of exams taken with that grade
     */
    public function __construct(
        public ?int $circleId,
        public ?string $circleName,
        public ?string $courseName,
        public int $pending,
        public int $done,
        public array $grades,
    ) {}

    public function countOf(ExamGrade $grade): int
    {
        return $this->grades[$grade->value] ?? 0;
    }
}
