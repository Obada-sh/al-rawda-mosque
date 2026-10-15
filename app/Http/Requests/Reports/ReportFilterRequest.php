<?php

namespace App\Http\Requests\Reports;

use App\Http\Requests\BaseRequest;

/**
 * What a report is narrowed to: a course, one of its circles, and a period.
 * Every part is optional; an empty filter means the whole mosque, all time.
 */
class ReportFilterRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'course_id' => ['nullable', 'integer', 'exists:courses,id'],
            'circle_id' => ['nullable', 'integer', 'exists:circles,id'],
            'from' => ['nullable', 'date'],
            'until' => ['nullable', 'date', 'after_or_equal:from'],
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
            'course_id' => 'الدورة',
            'circle_id' => 'الحلقة',
            'from' => 'من تاريخ',
            'until' => 'إلى تاريخ',
        ];
    }
}
