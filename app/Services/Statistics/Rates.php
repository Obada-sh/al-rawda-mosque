<?php

namespace App\Services\Statistics;

/**
 * The arithmetic shared by the report rows.
 */
final class Rates
{
    /**
     * Attended sessions out of the sessions that were held, as a percentage
     * with one decimal. Null when no session was held: there is no rate to
     * show, which is different from a rate of zero.
     */
    public static function attendance(int $attended, int $sessions): ?float
    {
        if ($sessions <= 0) {
            return null;
        }

        return round(min($attended, $sessions) * 100 / $sessions, 1);
    }

    /**
     * A total shared between a number of students, with one decimal.
     * Null when there is nobody to share it between.
     */
    public static function average(int $total, int $count): ?float
    {
        if ($count <= 0) {
            return null;
        }

        return round($total / $count, 1);
    }
}
