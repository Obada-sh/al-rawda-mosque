<?php

namespace App\Http\Requests\Cards;

use App\Http\Requests\BaseRequest;

/**
 * Which attendance cards to print: a whole course, one circle of it, or
 * chosen students.
 */
class PrintCardsRequest extends BaseRequest
{
    public const MAX_CARDS = 1000;

    public function rules(): array
    {
        return [
            'course_id' => ['required', 'integer', 'exists:courses,id'],
            'circle_id' => ['nullable', 'integer', 'exists:circles,id'],
            'enrollment_ids' => ['nullable', 'array', 'max:'.self::MAX_CARDS],
            'enrollment_ids.*' => ['integer', 'distinct'],
        ];
    }

    public function attributes(): array
    {
        return [
            'course_id' => 'الدورة',
            'circle_id' => 'الحلقة',
            'enrollment_ids' => 'الطلاب',
        ];
    }
}
