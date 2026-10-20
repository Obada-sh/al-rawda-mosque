<?php

namespace App\Http\Requests\Students;

use App\Http\Requests\BaseRequest;
use App\Http\Requests\Rules\Picture;
use App\Support\QuranPages;

/**
 * Creating and editing a student. Only the name is required; the father's
 * name, when given, tells apart students who carry the same name.
 *
 * The photo is optional: a picture to keep, or a wish to remove the one
 * the student has.
 */
class StudentRequest extends BaseRequest
{
    public const PHONE_PATTERN = '/^\+?[0-9][0-9\s\-]{5,19}$/';

    /** The form shrinks a photo before sending it; this is the room left for one it could not shrink. */
    public const PHOTO_MAX_KILOBYTES = 2048;

    protected array $digitFields = ['phone', 'guardian_phone', 'prior_memorized_pages'];

    public function rules(): array
    {
        return [
            'full_name' => ['required', 'string', 'min:2', 'max:255'],
            'father_name' => ['nullable', 'string', 'min:2', 'max:255'],
            'birth_date' => ['nullable', 'date', 'after:1900-01-01', 'before:today'],
            'phone' => ['nullable', 'string', 'max:30', 'regex:'.self::PHONE_PATTERN],
            'guardian_phone' => ['nullable', 'string', 'max:30', 'regex:'.self::PHONE_PATTERN],
            'address' => ['nullable', 'string', 'max:255'],
            'tajweed_level_id' => ['nullable', 'integer', 'exists:tajweed_levels,id'],
            'prior_memorized_pages' => ['nullable', 'integer', 'min:0', 'max:'.QuranPages::LAST],
            'notes' => ['nullable', 'string', 'max:5000'],
            'photo' => ['nullable', 'file', 'max:'.self::PHOTO_MAX_KILOBYTES, new Picture],
            'remove_photo' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'phone.regex' => 'رقم الطالب يجب أن يتكون من أرقام فقط (يمكن أن يبدأ بعلامة +).',
            'guardian_phone.regex' => 'رقم ولي الأمر يجب أن يتكون من أرقام فقط (يمكن أن يبدأ بعلامة +).',
            'birth_date.before' => 'تاريخ الميلاد يجب أن يكون قبل اليوم.',
            'photo.max' => 'حجم الصورة لا يتجاوز 2 ميغابايت.',
        ];
    }

    public function attributes(): array
    {
        return [
            'full_name' => 'اسم الطالب',
            'father_name' => 'اسم الأب',
            'birth_date' => 'تاريخ الميلاد',
            'phone' => 'رقم الطالب',
            'guardian_phone' => 'رقم ولي الأمر',
            'address' => 'مكان السكن',
            'tajweed_level_id' => 'مستوى التجويد',
            'prior_memorized_pages' => 'المحفوظ السابق',
            'notes' => 'ملاحظات',
            'photo' => 'الصورة',
        ];
    }
}
