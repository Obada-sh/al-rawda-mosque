<?php

namespace App\Actions\English\Attendance;

use App\Enums\SessionStatus;
use App\Models\English\EnglishSession;

final class ReopenEnglishSession
{
    public function handle(EnglishSession $session): EnglishSession
    {
        $session->fill(['status' => SessionStatus::Held])->save();

        return $session;
    }
}
