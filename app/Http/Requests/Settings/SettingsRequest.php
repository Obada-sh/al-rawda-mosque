<?php

namespace App\Http\Requests\Settings;

use App\Http\Requests\BaseRequest;

/**
 * The general settings of the system.
 */
class SettingsRequest extends BaseRequest
{
    public const MAX_REMINDER_DAYS = 365;

    protected array $digitFields = ['incomplete_profiles_reminder_days'];

    public function rules(): array
    {
        return [
            'mosque_name' => ['nullable', 'string', 'max:150'],
            'incomplete_profiles_reminder_days' => ['required', 'integer', 'min:1', 'max:'.self::MAX_REMINDER_DAYS],
        ];
    }

    public function attributes(): array
    {
        return [
            'mosque_name' => 'اسم الجامع',
            'incomplete_profiles_reminder_days' => 'عدد الأيام بين التذكيرين',
        ];
    }
}
