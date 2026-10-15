<?php

namespace App\Http\Requests\Backups;

use App\Http\Requests\BaseRequest;
use App\Services\Backup\BackupStore;

/**
 * The backup an action is asked to work on, by its file name. A name is
 * only ever one the system gives to a backup, so it cannot point outside
 * the backups folder.
 */
class BackupNameRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100', 'regex:'.BackupStore::NAME_PATTERN],
        ];
    }

    public function messages(): array
    {
        return [
            'name.regex' => 'اسم النسخة الاحتياطية غير صحيح.',
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'النسخة الاحتياطية',
        ];
    }
}
