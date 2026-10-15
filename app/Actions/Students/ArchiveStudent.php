<?php

namespace App\Actions\Students;

use App\Models\Student;

/**
 * Hides a student from daily work without losing any of their history.
 */
final class ArchiveStudent
{
    public function handle(Student $student): Student
    {
        if (! $student->isArchived()) {
            $student->forceFill(['archived_at' => now()])->save();
        }

        return $student;
    }
}
