<?php

namespace App\Http\Requests\English;

use App\Http\Requests\BaseRequest;
use App\Http\Requests\Cards\PrintCardsRequest;

/**
 * Which attendance cards of the English section to print: a whole course,
 * one hall of it, or chosen students.
 */
class EnglishPrintCardsRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'english_course_id' => ['required', 'integer', 'exists:english_courses,id'],
            'english_hall_id' => ['nullable', 'integer', 'exists:english_halls,id'],
            'english_enrollment_ids' => ['nullable', 'array', 'max:'.PrintCardsRequest::MAX_CARDS],
            'english_enrollment_ids.*' => ['integer', 'distinct'],
        ];
    }

    public function attributes(): array
    {
        return [
            'english_course_id' => 'الدورة',
            'english_hall_id' => 'القاعة',
            'english_enrollment_ids' => 'الطلاب',
        ];
    }
}
