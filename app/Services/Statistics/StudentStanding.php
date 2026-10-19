<?php

namespace App\Services\Statistics;

/**
 * What one student did in one course during the period of a report.
 */
final readonly class StudentStanding
{
    public function __construct(
        public int $enrollmentId,
        public int $studentId,
        public string $studentName,
        public int $courseId,
        public string $courseName,
        public int $circleId,
        public string $circleName,
        /** The day the student left the course (Y-m-d); null while they are in it. They stay in the report for the time they were in it. */
        public ?string $leftOn,
        /** Pages recited for the first time. */
        public int $newPages,
        /** Every page recited, repeated ones included. */
        public int $recitedPages,
        public int $points,
        /** Sessions the student attended. */
        public int $attended,
        /** Sessions that were held while the student was in the course. */
        public int $sessions,
        /**
         * The juz the student passed an exam of (السبر) in the period, in
         * this course: each juz counts once, and a retake is not a pass.
         */
        public int $examsPassed,
    ) {}

    public function hasLeft(): bool
    {
        return $this->leftOn !== null;
    }

    /**
     * Whether the student was still in the course at the end of that day.
     *
     * @param  string  $day  Y-m-d
     */
    public function wasEnrolledOn(string $day): bool
    {
        return $this->leftOn === null || $this->leftOn >= $day;
    }

    public function attendanceRate(): ?float
    {
        return Rates::attendance($this->attended, $this->sessions);
    }
}
