<?php

namespace App\Http\Requests\Backups;

use App\Http\Requests\BaseRequest;
use App\Services\Backup\DriveCopySettings;

/**
 * The set-up of the evening copy for Google Drive: the folder on this
 * computer that the Google Drive program sends to Drive (empty turns the
 * copy off), the address of the folder on drive.google.com (optional),
 * the time from which the copy of the day is put there, and for how many
 * days the copies are kept.
 *
 * Whether the folder exists and can be written in is checked by
 * SaveDriveCopySettings, on this computer.
 */
class DriveCopyRequest extends BaseRequest
{
    protected array $digitFields = ['time', 'keep_days'];

    public function rules(): array
    {
        return [
            'folder' => ['nullable', 'string', 'max:400'],
            'link' => ['nullable', 'string', 'max:500', 'url:https', 'starts_with:https://drive.google.com/'],
            'time' => ['required', 'date_format:H:i,H:i:s'],
            'keep_days' => ['required', 'integer', 'between:1,'.DriveCopySettings::MAX_KEEP_DAYS],
        ];
    }

    public function messages(): array
    {
        return [
            'link.url' => 'الصق رابط المجلد كما يظهر في المتصفح، ويبدأ بـ https://drive.google.com/',
            'link.starts_with' => 'الصق رابط المجلد كما يظهر في المتصفح، ويبدأ بـ https://drive.google.com/',
            'time.date_format' => 'اكتب الوقت بالساعة والدقيقة، مثل 20:00.',
            'keep_days.between' => 'عدد الأيام من 1 إلى '.DriveCopySettings::MAX_KEEP_DAYS.'.',
        ];
    }

    public function attributes(): array
    {
        return [
            'folder' => 'مجلد Google Drive',
            'link' => 'رابط المجلد',
            'time' => 'وقت النسخة',
            'keep_days' => 'عدد الأيام',
        ];
    }
}
