<?php

namespace App\Actions\Documents;

use App\Models\Enrollment;
use App\Models\Recitation;
use App\Services\Statistics\StudentStanding;

/**
 * What a student did in one course: the figures, every recitation and
 * every session.
 */
final readonly class StudentCourseFile
{
    /**
     * @param  Enrollment  $enrollment  with its course and circle
     * @param  list<Recitation>  $recitations  oldest first, with the teacher
     * @param  list<AttendanceDay>  $days  oldest first
     */
    public function __construct(
        public Enrollment $enrollment,
        public StudentStanding $standing,
        public array $recitations,
        public array $days,
    ) {}
}
