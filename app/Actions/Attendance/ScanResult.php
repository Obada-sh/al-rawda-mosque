<?php

namespace App\Actions\Attendance;

use App\Enums\ScanStatus;
use App\Models\Attendance;
use App\Models\Circle;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Teacher;
use App\Services\Teachers\TeacherCircles;
use App\Support\Dates;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * What happened when a card was scanned, with everything the scan screen
 * needs to show. A card is a student's (an enrollment) or a teacher's.
 */
final readonly class ScanResult
{
    /**
     * @param  Collection<int, Circle>|null  $teacherCircles  for a teacher's card: the active circles of its course the teacher is responsible for
     * @param  Course|null  $teacherCourse  for a teacher's card: the course it belongs to
     * @param  CarbonInterface|null  $day  the day the attendance is for, when it is not today
     */
    public function __construct(
        public ScanStatus $status,
        public ?Enrollment $enrollment = null,
        public ?Attendance $attendance = null,
        public ?Teacher $teacher = null,
        public ?Collection $teacherCircles = null,
        public ?Course $teacherCourse = null,
        public ?CarbonInterface $day = null,
    ) {}

    public function message(): string
    {
        return $this->status->label($this->day === null ? null : Dates::show($this->day));
    }

    public function isTeacher(): bool
    {
        return $this->teacher !== null;
    }

    /** The owner of the card: the student, or the teacher. */
    public function name(): ?string
    {
        return $this->teacher?->full_name ?? $this->studentName();
    }

    public function studentName(): ?string
    {
        return $this->enrollment?->student?->full_name;
    }

    /** The student's circle, or what the teacher does in which circles. */
    public function circleName(): ?string
    {
        if ($this->teacher !== null) {
            return $this->teacherCircles === null || $this->teacherCircles->isEmpty()
                ? null
                : TeacherCircles::roles($this->teacher, $this->teacherCircles);
        }

        return $this->enrollment?->circle?->name;
    }

    public function courseName(): ?string
    {
        if ($this->teacher !== null) {
            return $this->teacherCourse?->name;
        }

        return $this->enrollment?->course?->name;
    }
}
