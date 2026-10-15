<?php

namespace App\Http\Requests\Enrollments;

use App\Http\Requests\BaseRequest;

/**
 * Correcting the enrollment date, or recording / clearing the date the
 * student left the course.
 */
class EnrollmentDatesRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'enrolled_on' => ['required', 'date', 'before_or_equal:today'],
            'left_on' => ['nullable', 'date', 'after_or_equal:enrolled_on', 'before_or_equal:today'],
        ];
    }

    public function messages(): array
    {
        return [
            'left_on.after_or_equal' => 'تاريخ الخروج يجب أن يكون بعد تاريخ التسجيل.',
        ];
    }

    public function attributes(): array
    {
        return [
            'enrolled_on' => 'تاريخ التسجيل',
            'left_on' => 'تاريخ الخروج',
        ];
    }
}
