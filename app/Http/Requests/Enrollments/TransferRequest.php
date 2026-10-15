<?php

namespace App\Http\Requests\Enrollments;

use App\Http\Requests\BaseRequest;

/**
 * Moving a student to another circle of the same course.
 * The transfer date has no default: the admin must enter it.
 */
class TransferRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'to_circle_id' => ['required', 'integer', 'exists:circles,id'],
            'transferred_on' => ['required', 'date', 'before_or_equal:today'],
            'notes' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'transferred_on.required' => 'أدخل تاريخ النقل.',
            'transferred_on.before_or_equal' => 'تاريخ النقل لا يمكن أن يكون في المستقبل.',
        ];
    }

    public function attributes(): array
    {
        return [
            'to_circle_id' => 'الحلقة الجديدة',
            'transferred_on' => 'تاريخ النقل',
            'notes' => 'ملاحظات',
        ];
    }
}
