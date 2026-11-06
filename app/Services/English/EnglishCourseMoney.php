<?php

namespace App\Services\English;

/**
 * The money of one course, or of many: what its students have to pay,
 * what they paid and what remains, and how many stand where.
 */
final readonly class EnglishCourseMoney
{
    public function __construct(
        public ?int $courseId,
        public string $courseName,
        public int $students,
        public int $due,
        public int $paid,
        public int $remaining,
        public int $paidInFull,
        public int $partly,
        public int $unpaid,
    ) {}
}
