<?php

namespace App\Http\Requests\Documents;

use App\Actions\Documents\SheetOptions;
use App\Actions\Documents\StudentFiles;
use App\Http\Requests\BaseRequest;
use Illuminate\Validation\Rule;

/**
 * Whose files to print together, each student on pages of their own:
 *
 * - every student in the system (archived ones too, when asked);
 * - the students of a course, or of a circle, now; with every course of
 *   each student, or only that course (history);
 * - the students ticked in the list.
 */
class StudentFilesRequest extends BaseRequest
{
    public const MAX_CHOSEN = 500;

    public function rules(): array
    {
        return [
            'scope' => ['required', Rule::in(StudentFiles::SCOPES)],
            'course_id' => ['exclude_unless:scope,'.StudentFiles::COURSE, 'required', 'integer', 'exists:courses,id'],
            'circle_id' => ['exclude_unless:scope,'.StudentFiles::CIRCLE, 'required', 'integer', 'exists:circles,id'],
            'student_ids' => ['exclude_unless:scope,'.StudentFiles::CHOSEN, 'required', 'array', 'max:'.self::MAX_CHOSEN],
            'student_ids.*' => ['exclude_unless:scope,'.StudentFiles::CHOSEN, 'integer', 'distinct', 'exists:students,id'],
            'archived' => ['exclude_unless:scope,'.StudentFiles::ALL, 'nullable', 'boolean'],
            'history' => [
                'exclude_unless:scope,'.StudentFiles::COURSE.','.StudentFiles::CIRCLE,
                'nullable',
                Rule::in([SheetOptions::ALL_COURSES, SheetOptions::THIS_COURSE]),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'scope.required' => 'اختر الطلاب: الكل، أو طلاب دورة، أو طلاب حلقة.',
            'course_id.required' => 'اختر الدورة.',
            'circle_id.required' => 'اختر الحلقة.',
            'student_ids.required' => 'حدّد الطلاب أولاً.',
            'student_ids.max' => 'تُطبع ملفات '.self::MAX_CHOSEN.' طالب على الأكثر في المرة الواحدة.',
        ];
    }

    public function attributes(): array
    {
        return [
            'scope' => 'الطلاب',
            'course_id' => 'الدورة',
            'circle_id' => 'الحلقة',
            'student_ids' => 'الطلاب',
            'student_ids.*' => 'الطالب',
            'archived' => 'الطلاب المؤرشفون',
            'history' => 'السجلّ',
        ];
    }
}
