<?php

namespace App\Actions\English\Attendance;

use App\Models\English\EnglishAttendance;

/**
 * Removes an attendance recorded by mistake.
 */
final class DeleteEnglishAttendance
{
    public function handle(EnglishAttendance $attendance): void
    {
        $attendance->delete();
    }
}
