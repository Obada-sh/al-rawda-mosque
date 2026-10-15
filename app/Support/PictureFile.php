<?php

namespace App\Support;

/**
 * What kind of picture a file really is, read from the file itself: a
 * name and a declared type prove nothing. It needs no image extension of
 * PHP, which the mosque laptop may not have.
 */
final class PictureFile
{
    /** The kinds of picture the system keeps, and the ending their files get. */
    private const KINDS = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp'];

    /**
     * @return 'jpg'|'png'|'webp'|null null when the file is not one of those pictures
     */
    public static function kind(string $path): ?string
    {
        $size = self::measure($path);

        return $size === null ? null : (self::KINDS[$size[2]] ?? null);
    }

    /**
     * @return array{0: int, 1: int}|null width and height in pixels; null when the file is not such a picture
     */
    public static function dimensions(string $path): ?array
    {
        $size = self::measure($path);

        return $size === null || ! isset(self::KINDS[$size[2]]) ? null : [$size[0], $size[1]];
    }

    /**
     * @return array{0: int, 1: int, 2: int}|null
     */
    private static function measure(string $path): ?array
    {
        if ($path === '' || ! is_file($path)) {
            return null;
        }

        $size = @getimagesize($path);

        return $size === false || $size[0] < 1 || $size[1] < 1 ? null : [$size[0], $size[1], $size[2]];
    }
}
