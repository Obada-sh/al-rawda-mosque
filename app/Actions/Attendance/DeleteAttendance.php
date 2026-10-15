<?php

namespace App\Actions\Attendance;

use App\Models\Attendance;
use Illuminate\Support\Facades\DB;

/**
 * Removes an attendance together with the points it earned.
 */
final class DeleteAttendance
{
    public function handle(Attendance $attendance): void
    {
        DB::transaction(function () use ($attendance): void {
            $attendance->pointTransaction()->delete();
            $attendance->delete();
        });
    }
}
