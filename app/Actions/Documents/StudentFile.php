<?php

namespace App\Actions\Documents;

use App\Models\Student;

/**
 * A student's file, ready to print: their details, and what they did in
 * each of their courses (or in the one chosen).
 */
final readonly class StudentFile
{
    /**
     * @param  Student  $student  with their tajweed level
     * @param  list<StudentCourseFile>  $courses  the newest course first
     */
    public function __construct(
        public Student $student,
        public array $courses,
    ) {}
}
