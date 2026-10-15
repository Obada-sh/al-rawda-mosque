<?php

namespace App\Actions\Documents;

use App\Models\Attendance;
use App\Models\CourseSession;

/**
 * A session of the course the student was expected at, and whether they
 * came. Absence is not stored: a student is absent from a held session
 * they have no attendance in.
 */
final readonly class AttendanceDay
{
    public function __construct(
        public CourseSession $session,
        public ?Attendance $attendance,
    ) {}

    public function wasPresent(): bool
    {
        return $this->attendance !== null;
    }
}
