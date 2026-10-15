<?php

namespace App\Http\Requests\Teachers;

use App\Http\Requests\BaseRequest;
use App\Http\Requests\Rules\Picture;
use App\Http\Requests\Students\StudentRequest;

/**
 * Adding and editing a teacher. The photo is optional, for the teacher's
 * attendance card: a picture to keep, or a wish to remove the one they have.
 */
class TeacherRequest extends BaseRequest
{
    protected array $digitFields = ['phone'];

    public function rules(): array
    {
        return [
            'full_name' => ['required', 'string', 'min:2', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30', 'regex:'.StudentRequest::PHONE_PATTERN],
            'is_active' => ['sometimes', 'boolean'],
            'photo' => ['nullable', 'file', 'max:'.StudentRequest::PHOTO_MAX_KILOBYTES, new Picture],
            'remove_photo' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'phone.regex' => 'رقم الأستاذ يجب أن يتكون من أرقام فقط (يمكن أن يبدأ بعلامة +).',
            'photo.max' => 'حجم الصورة لا يتجاوز 2 ميغابايت.',
        ];
    }

    public function attributes(): array
    {
        return [
            'full_name' => 'اسم الأستاذ',
            'phone' => 'رقم الأستاذ',
            'is_active' => 'الحالة',
            'photo' => 'الصورة',
            'remove_photo' => 'إزالة الصورة',
        ];
    }
}
