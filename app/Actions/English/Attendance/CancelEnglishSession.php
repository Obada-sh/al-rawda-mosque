<?php

namespace App\Actions\English\Attendance;

use App\Enums\SessionStatus;
use App\Exceptions\BusinessRuleViolation;
use App\Models\English\EnglishSession;

/**
 * Marks a class day as cancelled (a holiday), so it no longer counts in
 * attendance rates. A day that already has attendance cannot be cancelled:
 * remove its attendance first.
 */
final class CancelEnglishSession
{
    /**
     * @throws BusinessRuleViolation session.has_attendance
     */
    public function handle(EnglishSession $session): EnglishSession
    {
        if ($session->attendances()->exists()) {
            throw BusinessRuleViolation::because(
                'session.has_attendance',
                'لا يمكن إلغاء جلسة فيها حضور مسجَّل. احذف الحضور أولاً.',
            );
        }

        $session->fill(['status' => SessionStatus::Cancelled])->save();

        return $session;
    }
}
