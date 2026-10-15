<?php

namespace App\Support;

use App\Enums\Weekday;
use Carbon\CarbonImmutable;
use DateTimeInterface;

/**
 * Date handling shared by the models and the actions, and the way dates
 * are written for the admin: always in numbers, the month included
 * ("07/10/2026", never the month's name).
 */
final class Dates
{
    /**
     * Eloquent cast for date-only columns.
     *
     * The plain "date" cast turns into a UTC instant when a model is
     * converted to an array (which is how the admin panel fills its forms).
     * East of UTC that instant falls on the previous day, so an edit form
     * would show, and then save, the wrong date. With an explicit format the
     * array holds the calendar date itself.
     */
    public const CAST = 'date:Y-m-d';

    /** How dates are shown to the admin: day / month / year. */
    public const DISPLAY_FORMAT = 'd/m/Y';

    /** How date-times are shown to the admin. */
    public const DISPLAY_FORMAT_WITH_TIME = 'd/m/Y H:i';

    /** How a month is shown to the admin: month / year. */
    public const MONTH_DISPLAY_FORMAT = 'm/Y';

    /**
     * Input dates arrive as strings from forms or as date objects from code
     * and tests; both are accepted.
     */
    public static function parse(DateTimeInterface|string $value): CarbonImmutable
    {
        return CarbonImmutable::parse($value)->startOfDay();
    }

    /**
     * @return string the date as Y-m-d
     */
    public static function day(DateTimeInterface|string $value): string
    {
        return self::parse($value)->toDateString();
    }

    public static function today(): CarbonImmutable
    {
        return CarbonImmutable::now()->startOfDay();
    }

    /**
     * A day as the admin reads it: "07/10/2026".
     */
    public static function show(DateTimeInterface|string $day): string
    {
        return self::parse($day)->format(self::DISPLAY_FORMAT);
    }

    /**
     * A day with the name of its weekday: "الأربعاء 07/10/2026".
     */
    public static function showWithWeekday(DateTimeInterface|string $day): string
    {
        $day = self::parse($day);

        return Weekday::fromDate($day)->label().' '.$day->format(self::DISPLAY_FORMAT);
    }

    /**
     * A month as the admin reads it: "10/2026".
     */
    public static function showMonth(int $year, int $month): string
    {
        return CarbonImmutable::create($year, $month, 1)->format(self::MONTH_DISPLAY_FORMAT);
    }
}
