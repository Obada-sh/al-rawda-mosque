<?php

namespace App\Http\Requests\English;

use App\Enums\English\PaymentState;
use App\Http\Requests\BaseRequest;
use Illuminate\Validation\Rule;

/**
 * What the report of the students' payments is narrowed to: a course, a
 * hall of it, where the students stand. Every part is optional.
 */
class EnglishBalanceFilterRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'english_course_id' => ['nullable', 'integer', 'exists:english_courses,id'],
            'english_hall_id' => ['nullable', 'integer', 'exists:english_halls,id'],
            'state' => ['nullable', Rule::enum(PaymentState::class)],
            'with_left' => ['nullable', 'boolean'],
        ];
    }

    public function attributes(): array
    {
        return [
            'english_course_id' => 'الدورة',
            'english_hall_id' => 'القاعة',
            'state' => 'الدفع',
            'with_left' => 'مع من خرجوا من الدورة',
        ];
    }
}
