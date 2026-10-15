<?php

namespace App\Support;

/**
 * File names built from what the admin typed (the name of a course...).
 */
final class FileName
{
    private const MAX_LENGTH = 120;

    /**
     * A name Windows accepts: without \ / : * ? " < > | or control
     * characters, without a dot or a space at its end, and not too long.
     * The extension is kept.
     */
    public static function safe(string $name): string
    {
        $extension = pathinfo($name, PATHINFO_EXTENSION);
        $base = $extension === '' ? $name : mb_substr($name, 0, -(mb_strlen($extension) + 1));

        $base = (string) preg_replace('/[\\\\\/:*?"<>|\x00-\x1F\x7F]+/u', '-', $base);
        $base = (string) preg_replace('/\s+/u', ' ', $base);
        $base = trim(mb_substr(trim($base, " .-\t"), 0, self::MAX_LENGTH), " .-\t");

        if ($base === '') {
            $base = 'file';
        }

        return $extension === '' ? $base : "{$base}.{$extension}";
    }
}
