<?php

namespace App\Http\Requests\English;

use App\Http\Requests\Attendance\BulkAttendanceRequest;
use App\Http\Requests\BaseRequest;

/**
 * The attendance of many students of an English course on one day: chosen
 * by name, typed (or scanned) as card numbers, or both.
 */
class EnglishBulkAttendanceRequest extends BaseRequest
{
    protected array $digitFields = ['card_codes'];

    public function rules(): array
    {
        return [
            'english_course_id' => ['required', 'integer', 'exists:english_courses,id'],
            'attended_on' => ['required', 'date', 'before_or_equal:today'],
            'english_enrollment_ids' => ['required_without:card_codes', 'nullable', 'array', 'max:'.BulkAttendanceRequest::MAX_STUDENTS],
            'english_enrollment_ids.*' => ['integer', 'distinct'],
            'card_codes' => ['required_without:english_enrollment_ids', 'nullable', 'string', 'max:'.BulkAttendanceRequest::MAX_CODES_LENGTH],
        ];
    }

    public function messages(): array
    {
        return [
            'attended_on.before_or_equal' => 'لا يمكن تسجيل حضور بتاريخ في المستقبل.',
            'english_enrollment_ids.required_without' => 'اختر طالباً واحداً على الأقل، أو اكتب أرقام بطاقاتهم.',
            'card_codes.required_without' => 'اكتب أرقام البطاقات، أو اختر الطلاب بأسمائهم.',
        ];
    }

    public function attributes(): array
    {
        return [
            'english_course_id' => 'الدورة',
            'attended_on' => 'تاريخ الحضور',
            'english_enrollment_ids' => 'الطلاب',
            'english_enrollment_ids.*' => 'الطالب',
            'card_codes' => 'أرقام البطاقات',
        ];
    }
}
