<?php

namespace App\Filament\Support\Presenters;

use App\Enums\CourseStatus;
use App\Enums\SessionStatus;
use App\Filament\Support\Options;
use App\Models\Course;
use App\Models\CourseSchedule;

/**
 * How courses and their sessions are shown in the panel.
 */
final class CoursePresenter
{
    public static function statusColor(CourseStatus $status): string
    {
        return match ($status) {
            CourseStatus::Active => 'success',
            CourseStatus::Finished => 'gray',
        };
    }

    public static function sessionStatusColor(SessionStatus $status): string
    {
        return match ($status) {
            SessionStatus::Held => 'success',
            SessionStatus::Cancelled => 'danger',
        };
    }

    /**
     * The attendance days of the course in week order, e.g. "السبت، الاثنين".
     */
    public static function days(Course $course): string
    {
        return $course->schedules
            ->sortBy(fn (CourseSchedule $schedule): int => Options::weekPosition($schedule->weekday))
            ->map(fn (CourseSchedule $schedule): string => $schedule->weekday->label())
            ->implode('، ');
    }
}
