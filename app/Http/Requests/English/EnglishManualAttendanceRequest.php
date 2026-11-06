<?php

namespace App\Http\Requests\English;

use App\Http\Requests\BaseRequest;

/**
 * Attendance the admin adds by hand in an English course, for any past
 * date or today.
 */
class EnglishManualAttendanceRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'english_enrollment_id' => ['required', 'integer', 'exists:english_enrollments,id'],
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
            'english_enrollment_id' => 'الطالب',
            'attended_on' => 'تاريخ الحضور',
        ];
    }
}
