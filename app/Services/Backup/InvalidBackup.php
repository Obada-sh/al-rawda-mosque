<?php

namespace App\Services\Backup;

use RuntimeException;

/**
 * A file that cannot be restored. The reason is a stable key; the screens
 * turn it into a message for the admin.
 */
final class InvalidBackup extends RuntimeException
{
    /** Not a backup of this system at all. */
    public const NOT_A_BACKUP = 'not_a_backup';

    /** A backup of this system that was cut short or altered. */
    public const DAMAGED = 'damaged';

    /** Taken by a newer version of the system than the one installed. */
    public const NEWER_VERSION = 'newer_version';

    private function __construct(public readonly string $reason, string $message)
    {
        parent::__construct($message);
    }

    public static function notABackup(string $detail): self
    {
        return new self(self::NOT_A_BACKUP, "Not a backup of this system: {$detail}");
    }

    public static function damaged(string $detail): self
    {
        return new self(self::DAMAGED, "The backup is damaged: {$detail}");
    }

    public static function newerVersion(string $detail): self
    {
        return new self(self::NEWER_VERSION, "The backup comes from a newer version: {$detail}");
    }
}
