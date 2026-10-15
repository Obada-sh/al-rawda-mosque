<?php

namespace Tests\Support;

use Illuminate\Http\UploadedFile;

/**
 * Real pictures for the tests, without the image extension of PHP: the
 * mosque laptop may not have it, so the tests must not need it either.
 */
final class Pictures
{
    /** A PNG of one pixel. */
    private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

    public static function pngBytes(): string
    {
        return (string) base64_decode(self::PNG, true);
    }

    public static function png(string $name = 'photo.png'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, self::pngBytes());
    }

    /**
     * Text that only calls itself a picture.
     */
    public static function pretender(string $name = 'photo.png'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, 'this is not a picture');
    }
}
