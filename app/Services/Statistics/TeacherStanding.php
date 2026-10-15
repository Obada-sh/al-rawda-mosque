<?php

namespace App\Services\Statistics;

/**
 * What a teacher heard from students during the period of a report.
 */
final readonly class TeacherStanding
{
    public function __construct(
        public int $teacherId,
        public string $teacherName,
        /** How many times the teacher heard a recitation. */
        public int $recitations,
        /** Pages heard, repeated ones included. */
        public int $pages,
        /** Pages heard that the student recited for the first time. */
        public int $newPages,
        /** Different students heard. */
        public int $students,
    ) {}
}
