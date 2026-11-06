<?php

namespace App\Services\Students;

use App\Enums\Section;
use App\Models\Student;

/**
 * Puts a student on a section's list, when they are enrolled in one of its
 * courses. A student enrolled in an English course who has no English
 * level yet is given the course's: their profile may then be complete.
 */
final class SectionMembership
{
    public function __construct(private readonly ProfileCompleteness $completeness) {}

    public function join(Student $student, Section $section, ?int $englishLevelId = null): void
    {
        $student->setAttribute($section->studentFlag(), true);

        if ($section === Section::English && $student->english_level_id === null && $englishLevelId !== null) {
            $student->english_level_id = $englishLevelId;
            $student->english_profile_complete = $this->completeness->isComplete($student->getAttributes(), Section::English);
        }

        if ($student->isDirty()) {
            $student->save();
        }
    }
}
