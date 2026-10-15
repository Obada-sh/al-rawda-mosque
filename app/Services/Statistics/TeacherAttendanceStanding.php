<?php

namespace App\Services\Statistics;

/**
 * How often a teacher came during the period of a report.
 */
final readonly class TeacherAttendanceStanding
{
    public function __construct(
        public int $teacherId,
        public string $teacherName,
        /** What the teacher does in the circles of the scope: "مشرف حلقة النور، مساعد حلقة الفجر". */
        public string $roles,
        /** Sessions held in the courses of the teacher's circles. */
        public int $sessions,
        /** Of those, the sessions the teacher attended. */
        public int $attended,
        /** The day of the teacher's last attendance in the scope (Y-m-d), or null. */
        public ?string $lastAttendedOn,
    ) {}

    public function absences(): int
    {
        return max(0, $this->sessions - $this->attended);
    }

    public function rate(): ?float
    {
        return Rates::attendance($this->attended, $this->sessions);
    }
}
