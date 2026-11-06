<?php

namespace App\Filament\English\Support;

use App\Enums\Section;
use App\Filament\Support\Options;
use App\Models\English\EnglishCourse;
use App\Models\English\EnglishCourseSchedule;
use App\Models\English\EnglishLevel;
use App\Models\Student;
use App\Services\Students\ProfileCompleteness;

/**
 * How the English section's records are shown in its panel.
 */
final class EnglishPresenter
{
    /**
     * The class days of the course in week order, e.g. "السبت، الاثنين".
     */
    public static function days(EnglishCourse $course): string
    {
        return $course->schedules
            ->sortBy(fn (EnglishCourseSchedule $schedule): int => Options::weekPosition($schedule->weekday))
            ->map(fn (EnglishCourseSchedule $schedule): string => $schedule->weekday->label())
            ->implode('، ');
    }

    /**
     * The address of the level's card design for the signed-in admin, or
     * null when it has none. The address changes with the design.
     */
    public static function cardBackgroundUrl(?EnglishLevel $level): ?string
    {
        return self::cardBackgroundUrlOf($level?->getKey(), $level?->card_background_path);
    }

    /**
     * The same, for whoever holds the number of the level and the name of
     * the file instead of the level (a card being printed).
     */
    public static function cardBackgroundUrlOf(?int $levelId, ?string $background): ?string
    {
        if ($levelId === null || blank($background)) {
            return null;
        }

        return route('filament.english.card-designs', [
            'level' => $levelId,
            'v' => pathinfo($background, PATHINFO_FILENAME),
        ]);
    }

    /**
     * The page that prints the attendance cards of a course: all of them,
     * those of a hall, or of chosen students.
     *
     * @param  list<int>  $enrollmentIds
     */
    public static function cardsUrl(int $courseId, ?int $hallId = null, array $enrollmentIds = []): string
    {
        return route('filament.english.cards', array_filter([
            'english_course_id' => $courseId,
            'english_hall_id' => $hallId,
            'english_enrollment_ids' => $enrollmentIds,
        ], fn (mixed $value): bool => $value !== null && $value !== []));
    }

    /**
     * "ينقصه: تاريخ الميلاد، مستوى اللغة الانجليزية", or null when the
     * student's English profile is complete.
     */
    public static function missingFields(Student $student): ?string
    {
        $missing = app(ProfileCompleteness::class)->missingLabels($student->getAttributes(), Section::English);

        return $missing === [] ? null : 'ينقصه: '.implode('، ', $missing);
    }
}
