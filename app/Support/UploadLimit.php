<?php

namespace App\Support;

/**
 * The largest file that can be uploaded on this computer.
 *
 * The system has a limit of its own for every kind of file, and PHP has
 * limits too (deploy\serve.bat sets them when it starts the system). A
 * file beyond PHP's never reaches the system, so the screens name the
 * smaller of the two before the admin picks a file.
 */
final class UploadLimit
{
    /**
     * @param  int  $wanted  the largest file the system itself accepts, in kilobytes
     */
    public static function kilobytes(int $wanted): int
    {
        return self::within($wanted, (string) ini_get('upload_max_filesize'), (string) ini_get('post_max_size'));
    }

    /**
     * The same, for given settings of PHP ("2M", "512M"; "0" means no limit).
     */
    public static function within(int $wanted, string ...$settings): int
    {
        foreach ($settings as $setting) {
            $bytes = ini_parse_quantity(trim($setting));

            if ($bytes > 0) {
                $wanted = min($wanted, intdiv($bytes, 1024));
            }
        }

        return $wanted;
    }
}
