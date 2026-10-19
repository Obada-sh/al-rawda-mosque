<?php

namespace App\Support;

/**
 * Keys of the settings table.
 */
final class SettingKey
{
    public const MOSQUE_NAME = 'mosque_name';

    /** Days between two "incomplete profiles" reminders. */
    public const INCOMPLETE_PROFILES_REMINDER_DAYS = 'incomplete_profiles_reminder_days';

    /** Date (Y-m-d) of the last "incomplete profiles" reminder. */
    public const INCOMPLETE_PROFILES_LAST_REMINDED_ON = 'incomplete_profiles_last_reminded_on';

    public const DEFAULT_REMINDER_DAYS = 7;

    /** Date (Y-m-d) the admin was last told that the automatic backup failed. */
    public const BACKUP_FAILURE_REPORTED_ON = 'backup_failure_reported_on';

    /*
    | The evening copy for Google Drive (DriveCopySetup is the only one to
    | read and write these): where it is put, its web address, when, and
    | for how many days the copies are kept; then how the last attempt went.
    */

    /** The folder on this computer that the Google Drive program sends to Drive; empty = no evening copy. */
    public const DRIVE_FOLDER = 'drive_folder';

    /** The address of the same folder on drive.google.com, to open it from the panel. Optional. */
    public const DRIVE_LINK = 'drive_link';

    /** H:i: the evening copy is due every day from that time. */
    public const DRIVE_TIME = 'drive_time';

    /** The copies of this many last days are kept. */
    public const DRIVE_KEEP_DAYS = 'drive_keep_days';

    /** Y-m-d H:i:s and file name of the last copy put in the folder. */
    public const DRIVE_COPIED_AT = 'drive_copied_at';

    public const DRIVE_COPIED_NAME = 'drive_copied_name';

    /** Y-m-d H:i:s and reason of the last attempt that failed, when it came after the last copy. */
    public const DRIVE_FAILED_AT = 'drive_failed_at';

    public const DRIVE_FAILURE = 'drive_failure';

    /** Dates (Y-m-d) the admin was last told that the copy failed, and that old copies could not be removed. */
    public const DRIVE_FAILURE_REPORTED_ON = 'drive_failure_reported_on';

    public const DRIVE_LEFTOVERS_REPORTED_ON = 'drive_leftovers_reported_on';
}
