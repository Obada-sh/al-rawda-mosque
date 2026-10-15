<?php

namespace App\Services\Teachers;

use App\Contracts\CardCodeGenerator;
use App\Models\Course;
use App\Models\Teacher;
use App\Models\TeacherCard;
use Illuminate\Database\UniqueConstraintViolationException;
use RuntimeException;

/**
 * The attendance card of a teacher in a course. A teacher gets one the
 * first time their cards of that course are printed, and keeps it for the
 * whole course: a printed card stays valid.
 */
final class TeacherCards
{
    public function __construct(private readonly CardCodeGenerator $codes) {}

    public function of(Teacher $teacher, Course $course): TeacherCard
    {
        $existing = $this->find($teacher, $course);

        if ($existing !== null) {
            return $existing;
        }

        try {
            return TeacherCard::query()->create([
                'course_id' => $course->getKey(),
                'teacher_id' => $teacher->getKey(),
                'card_code' => $this->codes->generate(),
            ]);
        } catch (UniqueConstraintViolationException) {
            // The same card was asked for twice at the same moment.
            return $this->find($teacher, $course) ?? throw new RuntimeException('The teacher\'s card could not be given.');
        }
    }

    private function find(Teacher $teacher, Course $course): ?TeacherCard
    {
        return TeacherCard::query()
            ->where('course_id', $course->getKey())
            ->where('teacher_id', $teacher->getKey())
            ->first();
    }
}
