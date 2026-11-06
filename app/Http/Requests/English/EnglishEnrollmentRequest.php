<?php

namespace App\Http\Requests\English;

use App\Http\Requests\BaseRequest;

/**
 * Enrolling a student in an English course, in the hall the admin picked.
 * The price is the student's own, when it is not the course's (a
 * discount): the reason is written with it.
 */
class EnglishEnrollmentRequest extends BaseRequest
{
    protected array $digitFields = ['price'];

    public function rules(): array
    {
        return [
            'student_id' => ['required', 'integer', 'exists:students,id'],
            'english_course_id' => ['required', 'integer', 'exists:english_courses,id'],
            'english_hall_id' => ['required', 'integer', 'exists:english_halls,id'],
            'enrolled_on' => ['required', 'date', 'before_or_equal:today'],
            ...EnglishPriceRequest::priceRules(),
        ];
    }

    public function messages(): array
    {
        return [
            'english_hall_id.required' => 'اختر القاعة التي ينتمي إليها الطالب.',
            'enrolled_on.before_or_equal' => 'تاريخ التسجيل لا يمكن أن يكون في المستقبل.',
            ...EnglishPriceRequest::priceMessages(),
        ];
    }

    public function attributes(): array
    {
        return [
            'student_id' => 'الطالب',
            'english_course_id' => 'الدورة',
            'english_hall_id' => 'القاعة',
            'enrolled_on' => 'تاريخ التسجيل',
            ...EnglishPriceRequest::priceAttributes(),
        ];
    }
}
