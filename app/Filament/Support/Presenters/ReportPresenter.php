<?php

namespace App\Filament\Support\Presenters;

/**
 * How report figures are written and coloured in the panel.
 */
final class ReportPresenter
{
    /** From this attendance rate up, attendance is good. */
    private const GOOD_ATTENDANCE = 80.0;

    /** Below this attendance rate, attendance is poor. */
    private const POOR_ATTENDANCE = 50.0;

    /**
     * "87.5%"; null when there is no rate to show.
     */
    public static function percent(?float $rate): ?string
    {
        return $rate === null ? null : self::decimal($rate).'%';
    }

    /**
     * A number with at most one decimal: "12", "12.5".
     */
    public static function decimal(?float $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return rtrim(rtrim(number_format($value, 1, '.', ','), '0'), '.');
    }

    /**
     * A quantity without the zeros it does not need: "2.500" is "2.5", "3.000" is "3".
     */
    public static function quantity(string $decimal): string
    {
        return str_contains($decimal, '.') ? rtrim(rtrim($decimal, '0'), '.') : $decimal;
    }

    public static function attendanceColor(?float $rate): string
    {
        return match (true) {
            $rate === null => 'gray',
            $rate >= self::GOOD_ATTENDANCE => 'success',
            $rate >= self::POOR_ATTENDANCE => 'warning',
            default => 'danger',
        };
    }

    /**
     * Gold, silver and bronze for the first three places.
     */
    public static function placeColor(int $place): string
    {
        return match ($place) {
            1 => 'warning',
            2 => 'gray',
            3 => 'bronze',
            default => 'primary',
        };
    }
}
