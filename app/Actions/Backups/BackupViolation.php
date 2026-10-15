<?php

namespace App\Actions\Backups;

use App\Exceptions\BusinessRuleViolation;
use App\Services\Backup\BackupStore;
use App\Services\Backup\InvalidBackup;

/**
 * Says in Arabic why a backup cannot be taken, found or restored.
 */
final class BackupViolation
{
    public const NOT_FOUND = 'backup.not_found';

    public const FOLDER_NOT_WRITABLE = 'backup.folder_not_writable';

    public const NOT_A_BACKUP = 'backup.not_a_backup';

    public const DAMAGED = 'backup.damaged';

    public const NEWER_VERSION = 'backup.newer_version';

    public const RESTORE_FAILED = 'backup.restore_failed';

    public static function from(InvalidBackup $exception): BusinessRuleViolation
    {
        return match ($exception->reason) {
            InvalidBackup::DAMAGED => BusinessRuleViolation::because(
                self::DAMAGED,
                'ملف النسخة الاحتياطية تالف أو ناقص، ولا يمكن الاستعادة منه.',
            ),
            InvalidBackup::NEWER_VERSION => BusinessRuleViolation::because(
                self::NEWER_VERSION,
                'هذه النسخة أُخذت من إصدار أحدث من النظام. حدّث النظام أولاً ثم استعدها.',
            ),
            default => BusinessRuleViolation::because(
                self::NOT_A_BACKUP,
                'هذا الملف ليس نسخة احتياطية من هذا النظام.',
            ),
        };
    }

    public static function notFound(): BusinessRuleViolation
    {
        return BusinessRuleViolation::because(self::NOT_FOUND, 'لم تعد هذه النسخة الاحتياطية موجودة في مجلد النسخ.');
    }

    public static function folderNotWritable(BackupStore $store): BusinessRuleViolation
    {
        return BusinessRuleViolation::because(
            self::FOLDER_NOT_WRITABLE,
            'تعذّرت الكتابة في مجلد النسخ الاحتياطية: '.$store->directory(),
        );
    }

    public static function restoreFailed(): BusinessRuleViolation
    {
        return BusinessRuleViolation::because(
            self::RESTORE_FAILED,
            'تعذّرت الاستعادة من هذه النسخة، وأُعيدت البيانات كما كانت قبل المحاولة.',
        );
    }
}
