<?php

namespace App\Http\Requests\English;

use App\Http\Requests\BaseRequest;

/**
 * The period of the English section's money report, and optionally one
 * course (its payments) and one department (its expenses).
 */
class EnglishFinanceFilterRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'from' => ['nullable', 'date'],
            'until' => ['nullable', 'date', 'after_or_equal:from'],
            'english_course_id' => ['nullable', 'integer', 'exists:english_courses,id'],
            'english_department_id' => ['nullable', 'integer', 'exists:english_departments,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'until.after_or_equal' => 'نهاية الفترة لا يمكن أن تكون قبل بدايتها.',
        ];
    }

    public function attributes(): array
    {
        return [
            'from' => 'من تاريخ',
            'until' => 'إلى تاريخ',
            'english_course_id' => 'الدورة',
            'english_department_id' => 'القسم',
        ];
    }
}
