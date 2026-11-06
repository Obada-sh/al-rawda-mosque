<?php

namespace App\Services\English;

/**
 * One line of the report of the students' payments: a student in a course,
 * and where they stand with its price.
 */
final readonly class EnglishStudentBalance
{
    public function __construct(
        public int $enrollmentId,
        public int $studentId,
        public string $studentName,
        public int $courseId,
        public string $courseName,
        public string $hallName,
        public EnglishBalance $balance,
        public ?string $priceNote,
        public bool $hasLeft,
    ) {}
}
