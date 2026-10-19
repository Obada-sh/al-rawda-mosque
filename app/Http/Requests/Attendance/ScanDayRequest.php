<?php

namespace App\Http\Requests\Attendance;

use App\Http\Requests\BaseRequest;

/**
 * The day the scan desk records the cards for: today, or an earlier day
 * whose cards are entered late.
 */
class ScanDayRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'day' => ['required', 'date', 'before_or_equal:today'],
        ];
    }

    public function messages(): array
    {
        return [
            'day.required' => 'اختر تاريخ الحضور قبل مسح البطاقات.',
            'day.before_or_equal' => 'لا يمكن تسجيل حضور بتاريخ في المستقبل.',
        ];
    }

    public function attributes(): array
    {
        return [
            'day' => 'تاريخ الحضور',
        ];
    }
}
