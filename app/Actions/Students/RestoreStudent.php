<?php

namespace App\Actions\Students;

use App\Models\Student;

final class RestoreStudent
{
    public function handle(Student $student): Student
    {
        if ($student->isArchived()) {
            $student->forceFill(['archived_at' => null])->save();
        }

        return $student;
    }
}
