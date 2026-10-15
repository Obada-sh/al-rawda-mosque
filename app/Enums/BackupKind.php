<?php

namespace App\Enums;

/**
 * Why a backup was taken. The kind is part of the file name.
 */
enum BackupKind: string
{
    /** The first time the system is used each day. Only the newest ones are kept. */
    case Automatic = 'auto';

    /** The admin pressed "backup now". */
    case Manual = 'manual';

    /** Taken by the system just before a restore, so the restore can be undone. */
    case BeforeRestore = 'before-restore';

    /** Taken by the system just before an update changes the database. */
    case BeforeUpdate = 'before-update';

    /** A file the admin brought back from outside (a USB stick). */
    case Uploaded = 'uploaded';

    public function label(): string
    {
        return match ($this) {
            self::Automatic => 'تلقائية',
            self::Manual => 'يدوية',
            self::BeforeRestore => 'قبل استعادة',
            self::BeforeUpdate => 'قبل تحديث',
            self::Uploaded => 'مرفوعة',
        };
    }
}
