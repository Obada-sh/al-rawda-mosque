<?php

namespace App\Http\Requests\Finance;

use App\Http\Requests\BaseRequest;

/**
 * What the expenses report is narrowed to: a department and a period.
 * Every part is optional.
 */
class ExpenseFilterRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
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
            'department_id' => 'القسم',
            'from' => 'من تاريخ',
            'until' => 'إلى تاريخ',
        ];
    }
}
