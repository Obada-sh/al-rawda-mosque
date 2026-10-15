<?php

namespace App\Actions\Attendance;

use App\Enums\SessionStatus;
use App\Exceptions\BusinessRuleViolation;
use App\Models\CourseSession;

/**
 * Marks a session as cancelled (a holiday), so it no longer counts in
 * attendance rates. A session that already has attendance (of students or
 * of teachers) cannot be cancelled: remove its attendance first.
 */
final class CancelSession
{
    /**
     * @throws BusinessRuleViolation session.has_attendance
     */
    public function handle(CourseSession $session): CourseSession
    {
        if ($session->attendances()->exists() || $session->teacherAttendances()->exists()) {
            throw BusinessRuleViolation::because(
                'session.has_attendance',
                'لا يمكن إلغاء جلسة فيها حضور مسجَّل. احذف الحضور أولاً.',
            );
        }

        $session->fill(['status' => SessionStatus::Cancelled])->save();

        return $session;
    }
}
