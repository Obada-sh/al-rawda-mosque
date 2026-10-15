<?php

namespace App\Http\Requests\Attendance;

use App\Http\Requests\BaseRequest;

/**
 * Attendance the admin adds by hand, for any past date or today.
 */
class ManualAttendanceRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'enrollment_id' => ['required', 'integer', 'exists:enrollments,id'],
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
            'enrollment_id' => 'الطالب',
            'attended_on' => 'تاريخ الحضور',
        ];
    }
}
