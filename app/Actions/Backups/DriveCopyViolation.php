<?php

namespace App\Actions\Backups;

use App\Exceptions\BusinessRuleViolation;

/**
 * Says in Arabic why the evening copy for Google Drive cannot be set up
 * or put in its folder.
 */
final class DriveCopyViolation
{
    public const OFF = 'drive.off';

    public const DEMO = 'drive.demo';

    public const NOT_A_FULL_PATH = 'drive.folder_not_a_full_path';

    public const FOLDER_MISSING = 'drive.folder_missing';

    public const INSIDE_SYSTEM = 'drive.folder_inside_system';

    public const NOT_WRITABLE = 'drive.folder_not_writable';

    public const NOT_COPIED = 'drive.not_copied';

    public static function off(): BusinessRuleViolation
    {
        return BusinessRuleViolation::because(self::OFF, 'النسخة المسائية في Google Drive غير مفعّلة. اختر مجلدها أولاً من «إعداد Google Drive».');
    }

    public static function demo(): BusinessRuleViolation
    {
        return BusinessRuleViolation::because(self::DEMO, 'النسخة التجريبية لا تضع نسخاً في Google Drive: بياناتها مخترعة.');
    }

    public static function notAFullPath(): BusinessRuleViolation
    {
        return BusinessRuleViolation::because(self::NOT_A_FULL_PATH, 'اكتب مكان المجلد كاملاً، مثل: G:\My Drive\نسخ الروضة');
    }

    public static function folderMissing(string $folder): BusinessRuleViolation
    {
        return BusinessRuleViolation::because(
            self::FOLDER_MISSING,
            "لم يُعثر على المجلد {$folder} على هذا الحاسوب. تأكّد أن برنامج Google Drive يعمل ومسجَّل الدخول، وأن المجلد موجود فيه.",
        );
    }

    public static function insideSystem(): BusinessRuleViolation
    {
        return BusinessRuleViolation::because(self::INSIDE_SYSTEM, 'اختر مجلداً من مجلدات Google Drive، خارج مجلد النظام ومجلد نسخه الاحتياطية.');
    }

    public static function notWritable(string $folder): BusinessRuleViolation
    {
        return BusinessRuleViolation::because(self::NOT_WRITABLE, "تعذّرت الكتابة في المجلد {$folder}.");
    }

    public static function notCopied(string $folder): BusinessRuleViolation
    {
        return BusinessRuleViolation::because(
            self::NOT_COPIED,
            "تعذّر وضع النسخة في المجلد {$folder}، أو لم تطابق النسخة الأصلية بعد وضعها. هل في القرص مساحة كافية؟",
        );
    }
}
