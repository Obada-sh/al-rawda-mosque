<?php

namespace App\Http\Requests\Courses;

use App\Http\Requests\BaseRequest;
use App\Http\Requests\Rules\Picture;
use Illuminate\Validation\Rule;

/**
 * Creating and editing a circle inside a course.
 *
 * The logo is optional: a picture to keep (PNG with a see-through
 * background prints best on the card), or a wish to remove the one the
 * circle has.
 *
 * Context: course_id => the course the circle belongs to (required),
 *          ignore_id => the circle being edited (null when creating).
 */
class CircleRequest extends BaseRequest
{
    public const LOGO_MAX_KILOBYTES = 2048;

    public function rules(): array
    {
        return [
            'name' => [
                'required', 'string', 'max:150',
                Rule::unique('circles', 'name')
                    ->where('course_id', $this->context('course_id'))
                    ->ignore($this->context('ignore_id')),
            ],
            'supervisor_teacher_id' => ['nullable', 'integer', 'exists:teachers,id'],
            'assistant_teacher_id' => ['nullable', 'integer', 'exists:teachers,id', 'different:supervisor_teacher_id'],
            'is_active' => ['sometimes', 'boolean'],
            'logo' => ['nullable', 'file', 'max:'.self::LOGO_MAX_KILOBYTES, new Picture],
            'remove_logo' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.unique' => 'يوجد في هذه الدورة حلقة بنفس الاسم.',
            'assistant_teacher_id.different' => 'المساعد يجب أن يكون غير المشرف.',
            'logo.max' => 'حجم الشعار لا يتجاوز 2 ميغابايت.',
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'اسم الحلقة',
            'supervisor_teacher_id' => 'المشرف',
            'assistant_teacher_id' => 'المساعد',
            'is_active' => 'الحالة',
            'logo' => 'شعار الحلقة',
            'remove_logo' => 'إزالة الشعار',
        ];
    }
}
