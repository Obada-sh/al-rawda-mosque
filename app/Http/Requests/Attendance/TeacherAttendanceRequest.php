<?php

namespace App\Http\Requests\Attendance;

use App\Http\Requests\BaseRequest;

/**
 * The attendance of a teacher added by hand: which teacher, in which
 * course, on which day.
 */
class TeacherAttendanceRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'teacher_id' => ['required', 'integer', 'exists:teachers,id'],
            'course_id' => ['required', 'integer', 'exists:courses,id'],
            'attended_on' => ['required', 'date', 'before_or_equal:today'],
        ];
    }

    public function messages(): array
    {
        return [
            'attended_on.before_or_equal' => 'لا يمكن تسجيل حضور بتاريخ في المستقبل.',
        ];
    }

    public function attributes(): array
    {
        return [
            'teacher_id' => 'الأستاذ',
            'course_id' => 'الدورة',
            'attended_on' => 'تاريخ الحضور',
        ];
    }
}
