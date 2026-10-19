<?php

namespace App\Http\Requests\Documents;

use App\Actions\Documents\SheetOptions;
use App\Http\Requests\BaseRequest;
use Illuminate\Validation\Rule;

/**
 * Which circles to print a sheet of: one circle, or every circle of a
 * course. A circle is named on its own, since it belongs to one course.
 *
 * The list of students also says whose records it shows (this course, or
 * every course) and which columns (see SheetOptions).
 */
class CircleSheetRequest extends BaseRequest
{
    public function rules(): array
    {
        $rules = [
            'circle_id' => ['nullable', 'integer', 'exists:circles,id'],
            'course_id' => ['required_without:circle_id', 'prohibits:circle_id', 'nullable', 'integer', 'exists:courses,id'],
            'history' => ['nullable', Rule::in([SheetOptions::THIS_COURSE, SheetOptions::ALL_COURSES])],
        ];

        foreach (SheetOptions::COLUMNS as $column) {
            $rules[$column] = ['nullable', 'boolean'];
        }

        return $rules;
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
            'history' => 'السجلّ',
            'phones' => 'أرقام الهواتف',
            'pages' => 'الصفحات المحفوظة',
            'exams' => 'السبر',
            'attendance' => 'الحضور',
        ];
    }
}
