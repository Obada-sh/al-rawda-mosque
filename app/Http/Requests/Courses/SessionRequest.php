<?php

namespace App\Http\Requests\Courses;

use App\Http\Requests\BaseRequest;

/**
 * Opening an extra session (a class day outside the weekly timetable).
 */
class SessionRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'session_date' => ['required', 'date', 'before_or_equal:today'],
        ];
    }

    public function messages(): array
    {
        return [
            'session_date.before_or_equal' => 'لا يمكن فتح جلسة بتاريخ في المستقبل.',
        ];
    }

    public function attributes(): array
    {
        return [
            'session_date' => 'تاريخ الجلسة',
        ];
    }
}
