<?php

namespace App\Http\Requests\Documents;

use App\Http\Requests\BaseRequest;

/**
 * Which circles to print a sheet of: one circle, or every circle of a
 * course. A circle is named on its own, since it belongs to one course.
 */
class CircleSheetRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'circle_id' => ['nullable', 'integer', 'exists:circles,id'],
            'course_id' => ['required_without:circle_id', 'prohibits:circle_id', 'nullable', 'integer', 'exists:courses,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'course_id.required_without' => 'اختر الدورة أو الحلقة.',
            'course_id.prohibits' => 'تُختار الحلقة وحدها أو الدورة كلها، لا الاثنتان معاً.',
        ];
    }

    public function attributes(): array
    {
        return [
            'circle_id' => 'الحلقة',
            'course_id' => 'الدورة',
        ];
    }
}
