<?php

namespace App\Http\Requests\Documents;

use App\Actions\Documents\SheetOptions;
use App\Http\Requests\BaseRequest;
use Illuminate\Validation\Rule;

/**
 * The sheet of the mosque's students, without choosing a circle: every
 * student in the system with their records in every course (archived
 * students too, when asked), or the students of one course with what
 * they did in it. The columns are those of SheetOptions.
 */
class MosqueSheetRequest extends BaseRequest
{
    public function rules(): array
    {
        $rules = [
            'history' => ['required', Rule::in([SheetOptions::ALL_COURSES, SheetOptions::THIS_COURSE])],
            'course_id' => ['exclude_unless:history,'.SheetOptions::THIS_COURSE, 'required', 'integer', 'exists:courses,id'],
            'archived' => ['exclude_unless:history,'.SheetOptions::ALL_COURSES, 'nullable', 'boolean'],
        ];

        foreach (SheetOptions::COLUMNS as $column) {
            $rules[$column] = ['nullable', 'boolean'];
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'history.required' => 'اختر: كل الدورات أو دورة واحدة.',
            'course_id.required' => 'اختر الدورة.',
        ];
    }

    public function attributes(): array
    {
        return [
            'history' => 'السجلّ',
            'course_id' => 'الدورة',
            'archived' => 'الطلاب المؤرشفون',
            'phones' => 'أرقام الهواتف',
            'pages' => 'الصفحات المحفوظة',
            'exams' => 'السبر',
            'attendance' => 'الحضور',
        ];
    }
}
