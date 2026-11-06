<?php

namespace App\Http\Requests\English;

use App\Http\Requests\BaseRequest;
use App\Http\Requests\Students\StudentRequest;

/**
 * Adding and editing a teacher of the English section.
 */
class EnglishTeacherRequest extends BaseRequest
{
    protected array $digitFields = ['phone'];

    public function rules(): array
    {
        return [
            'full_name' => ['required', 'string', 'min:2', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30', 'regex:'.StudentRequest::PHONE_PATTERN],
            'is_active' => ['sometimes', 'boolean'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }

    public function messages(): array
    {
        return [
            'phone.regex' => 'رقم الأستاذ يجب أن يتكون من أرقام فقط (يمكن أن يبدأ بعلامة +).',
        ];
    }

    public function attributes(): array
    {
        return [
            'full_name' => 'اسم الأستاذ',
            'phone' => 'رقم الأستاذ',
            'is_active' => 'الحالة',
            'notes' => 'ملاحظات',
        ];
    }
}
