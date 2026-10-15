<?php

namespace App\Http\Requests\Backups;

use App\Http\Requests\BaseRequest;

/**
 * A backup file brought back from outside (a USB stick).
 *
 * Only the envelope is checked here; the file itself is opened and
 * checked part by part before it is accepted.
 */
class BackupUploadRequest extends BaseRequest
{
    public const MAX_KILOBYTES = 512000;

    public function rules(): array
    {
        return [
            'backup' => ['required', 'file', 'extensions:zip', 'max:'.self::MAX_KILOBYTES],
        ];
    }

    public function messages(): array
    {
        return [
            'backup.extensions' => 'اختر ملف نسخة احتياطية بصيغة ‎.zip.',
            'backup.max' => 'حجم الملف لا يتجاوز 500 ميغابايت.',
        ];
    }

    public function attributes(): array
    {
        return [
            'backup' => 'ملف النسخة الاحتياطية',
        ];
    }
}
