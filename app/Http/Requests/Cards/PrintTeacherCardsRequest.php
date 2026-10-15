<?php

namespace App\Http\Requests\Cards;

use App\Http\Requests\BaseRequest;

/**
 * Which teachers' cards to print: those of a course (every teacher who
 * supervises or assists an active circle of it), or chosen ones of them.
 */
class PrintTeacherCardsRequest extends BaseRequest
{
    public const MAX_CARDS = 500;

    public function rules(): array
    {
        return [
            'course_id' => ['required', 'integer', 'exists:courses,id'],
            'teacher_ids' => ['nullable', 'array', 'max:'.self::MAX_CARDS],
            'teacher_ids.*' => ['integer', 'distinct'],
        ];
    }

    public function attributes(): array
    {
        return [
            'course_id' => 'الدورة',
            'teacher_ids' => 'الأساتذة',
        ];
    }
}
