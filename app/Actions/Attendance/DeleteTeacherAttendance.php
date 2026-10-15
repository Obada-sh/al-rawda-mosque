<?php

namespace App\Actions\Attendance;

use App\Models\TeacherAttendance;

/**
 * Removes a teacher's attendance recorded by mistake. Teachers earn no
 * points, so nothing else changes.
 */
final class DeleteTeacherAttendance
{
    public function handle(TeacherAttendance $attendance): void
    {
        $attendance->delete();
    }
}
