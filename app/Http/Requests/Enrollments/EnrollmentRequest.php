<?php

namespace App\Http\Requests\Enrollments;

use App\Http\Requests\BaseRequest;

/**
 * Enrolling a student in a course. The admin always picks the circle:
 * students are never placed in circles automatically.
 */
class EnrollmentRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'student_id' => ['required', 'integer', 'exists:students,id'],
            'course_id' => ['required', 'integer', 'exists:courses,id'],
            'circle_id' => ['required', 'integer', 'exists:circles,id'],
            'enrolled_on' => ['required', 'date', 'before_or_equal:today'],
        ];
    }

    public function messages(): array
    {
        return [
            'circle_id.required' => 'اختر الحلقة التي ينتمي إليها الطالب.',
            'enrolled_on.before_or_equal' => 'تاريخ التسجيل لا يمكن أن يكون في المستقبل.',
        ];
    }

    public function attributes(): array
    {
        return [
            'student_id' => 'الطالب',
            'course_id' => 'الدورة',
            'circle_id' => 'الحلقة',
            'enrolled_on' => 'تاريخ التسجيل',
        ];
    }
}
