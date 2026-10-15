<?php

namespace App\Http\Requests\Documents;

use App\Http\Requests\BaseRequest;
use Illuminate\Validation\Rule;

/**
 * Which part of a student's file to print: every course of the student,
 * or one of them.
 *
 * Context: student_id (the student whose file it is).
 */
class StudentFileRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'course_id' => [
                'nullable',
                'integer',
                Rule::exists('enrollments', 'course_id')->where('student_id', (int) $this->context('student_id')),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'course_id.exists' => 'الطالب غير مسجَّل في هذه الدورة.',
        ];
    }

    public function attributes(): array
    {
        return [
            'course_id' => 'الدورة',
        ];
    }
}
