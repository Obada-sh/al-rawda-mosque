<?php

namespace App\Actions\Attendance;

use App\Enums\SessionStatus;
use App\Models\CourseSession;

final class ReopenSession
{
    public function handle(CourseSession $session): CourseSession
    {
        $session->fill(['status' => SessionStatus::Held])->save();

        return $session;
    }
}
