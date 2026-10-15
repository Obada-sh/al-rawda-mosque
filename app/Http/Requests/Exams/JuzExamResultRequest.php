<?php

namespace App\Http\Requests\Exams;

use App\Enums\ExamGrade;
use App\Http\Requests\BaseRequest;
use Illuminate\Validation\Rule;

/**
 * Entering the result of an exam.
 */
class JuzExamResultRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'exam_date' => ['required', 'date', 'before_or_equal:today'],
            'examiner_teacher_id' => ['required', 'integer', 'exists:teachers,id'],
            'grade' => ['required', Rule::enum(ExamGrade::class)],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }

    public function messages(): array
    {
        return [
            'exam_date.before_or_equal' => 'تاريخ السبر لا يمكن أن يكون في المستقبل.',
        ];
    }

    public function attributes(): array
    {
        return [
            'exam_date' => 'تاريخ السبر',
            'examiner_teacher_id' => 'الأستاذ',
            'grade' => 'التقييم',
            'notes' => 'ملاحظات',
        ];
    }
}
