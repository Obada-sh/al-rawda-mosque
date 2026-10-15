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
}
