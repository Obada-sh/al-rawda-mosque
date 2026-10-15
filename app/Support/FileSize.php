<?php

namespace App\Support;

/**
 * The size of a file, as the panel says it.
 */
final class FileSize
{
    private const KILOBYTE = 1024;

    private const MEGABYTE = 1024 * 1024;

    /**
     * "850 كيلوبايت", "3.4 ميغابايت", "500 ميغابايت".
     */
    public static function arabic(int $bytes): string
    {
        if ($bytes < self::MEGABYTE) {
            return max(1, (int) ceil($bytes / self::KILOBYTE)).' كيلوبايت';
        }

        $megabytes = number_format($bytes / self::MEGABYTE, 1, '.', '');

        return (str_ends_with($megabytes, '.0') ? substr($megabytes, 0, -2) : $megabytes).' ميغابايت';
    }
}
