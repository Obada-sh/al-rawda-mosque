<?php

namespace App\Http\Requests\Attendance;

use App\Http\Requests\BaseRequest;

/**
 * What the scanner types: the digits of an attendance card.
 */
class ScanAttendanceRequest extends BaseRequest
{
    protected array $digitFields = ['card_code'];

    public function rules(): array
    {
        return [
            'card_code' => ['required', 'string', 'regex:/^[0-9]{6,20}$/'],
        ];
    }

    public function attributes(): array
    {
        return [
            'card_code' => 'رقم البطاقة',
        ];
    }
}
