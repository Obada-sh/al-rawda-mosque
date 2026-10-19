<?php

namespace App\Enums;

/**
 * Every outcome of scanning an attendance card.
 *
 * Scanning never throws for an expected situation: the scan screen needs a
 * result it can colour, not an exception.
 */
enum ScanStatus: string
{
    case Recorded = 'recorded';
    case AlreadyRecorded = 'already_recorded';
    case UnknownCard = 'unknown_card';
    case InactiveEnrollment = 'inactive_enrollment';
    case BeforeEnrollment = 'before_enrollment';
    case CourseNotRunning = 'course_not_running';
    case NotScheduledDay = 'not_scheduled_day';
    case SessionCancelled = 'session_cancelled';
    case InactiveTeacher = 'inactive_teacher';
    case TeacherWithoutCircle = 'teacher_without_circle';

    public function isSuccess(): bool
    {
        return $this === self::Recorded;
    }

    public function isWarning(): bool
    {
        return $this === self::AlreadyRecorded;
    }

    public function isError(): bool
    {
        return ! $this->isSuccess() && ! $this->isWarning();
    }

    /**
     * What the desk says. A card scanned for an earlier day (the date of
     * the desk was changed) is told that day.
     *
     * @param  string|null  $day  the day the attendance was for, as shown (d/m/Y); null for today
     */
    public function label(?string $day = null): string
    {
        if ($day !== null) {
            return match ($this) {
                self::Recorded => "تم تسجيل الحضور ليوم {$day}",
                self::AlreadyRecorded => "الحضور مسجَّل مسبقاً يوم {$day}",
                self::CourseNotRunning => "الدورة لم تكن جارية يوم {$day}",
                self::NotScheduledDay => "يوم {$day} ليس يوم دوام لهذه الدورة",
                self::SessionCancelled => "جلسة يوم {$day} ملغاة",
                self::BeforeEnrollment => "يوم {$day} قبل تسجيل الطالب في الدورة",
                default => $this->label(),
            };
        }

        return match ($this) {
            self::Recorded => 'تم تسجيل الحضور',
            self::AlreadyRecorded => 'الحضور مسجَّل مسبقاً اليوم',
            self::UnknownCard => 'بطاقة غير معروفة',
            self::InactiveEnrollment => 'هذا الطالب غير نشط في الدورة',
            self::BeforeEnrollment => 'تسجيل الطالب في الدورة يبدأ بعد اليوم',
            self::CourseNotRunning => 'الدورة غير جارية اليوم',
            self::NotScheduledDay => 'اليوم ليس يوم دوام لهذه الدورة',
            self::SessionCancelled => 'جلسة اليوم ملغاة',
            self::InactiveTeacher => 'هذا الأستاذ غير نشط',
            self::TeacherWithoutCircle => 'الأستاذ ليس مشرفاً ولا مساعداً في حلقة نشطة من دورة هذه البطاقة',
        };
    }
}
