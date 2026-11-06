<?php

namespace App\Http\Requests\English;

use App\Http\Requests\BaseRequest;
use Illuminate\Validation\Rule;

/**
 * Creating and editing a hall of an English course, with its teacher and
 * its assistant (both from the English section's teachers).
 *
 * Context: english_course_id => the course the hall belongs to (required),
 *          ignore_id => the hall being edited (null when creating).
 */
class EnglishHallRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'name' => [
                'required', 'string', 'max:150',
                Rule::unique('english_halls', 'name')
                    ->where('english_course_id', $this->context('english_course_id'))
                    ->ignore($this->context('ignore_id')),
            ],
            'teacher_id' => ['nullable', 'integer', 'exists:english_teachers,id'],
            'assistant_id' => ['nullable', 'integer', 'exists:english_teachers,id', 'different:teacher_id'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.unique' => 'يوجد في هذه الدورة قاعة بنفس الاسم.',
            'assistant_id.different' => 'المساعد يجب أن يكون غير الأستاذ.',
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'اسم القاعة',
            'teacher_id' => 'الأستاذ',
            'assistant_id' => 'المساعد',
            'is_active' => 'الحالة',
        ];
    }
}
