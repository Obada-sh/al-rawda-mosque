<?php

namespace App\Http\Requests\Documents;

use App\Actions\Documents\MosqueSheet;
use App\Actions\Documents\SheetOptions;
use App\Http\Requests\BaseRequest;
use Illuminate\Validation\Rule;

/**
 * The sheet of the mosque's students, without choosing a circle: every
 * student in the system with their records in every course (archived
 * students too, when asked), or the students of one course with what
 * they did in it. In one table by name (the plain address), or circle by
 * circle (order=circles). The columns are those of SheetOptions.
 */
class MosqueSheetRequest extends BaseRequest
{
    public function rules(): array
    {
        $rules = [
            'history' => ['required', Rule::in([SheetOptions::ALL_COURSES, SheetOptions::THIS_COURSE])],
            'course_id' => ['exclude_unless:history,'.SheetOptions::THIS_COURSE, 'required', 'integer', 'exists:courses,id'],
            'archived' => ['exclude_unless:history,'.SheetOptions::ALL_COURSES, 'nullable', 'boolean'],
            'order' => ['nullable', Rule::in([MosqueSheet::BY_NAME, MosqueSheet::BY_CIRCLE])],
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
            'order' => 'ترتيب الطلاب',
            'phones' => 'أرقام الهواتف',
            'pages' => 'الصفحات المحفوظة',
            'exams' => 'السبر',
            'attendance' => 'الحضور',
            'notes' => 'الملاحظات',
        ];
    }
}
