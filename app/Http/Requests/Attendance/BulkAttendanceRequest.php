<?php

namespace App\Http\Requests\Attendance;

use App\Http\Requests\BaseRequest;

/**
 * The attendance of many students of a course on one day: chosen by
 * name, typed (or scanned) as card numbers, or both.
 */
class BulkAttendanceRequest extends BaseRequest
{
    /** At most this many students are chosen by name at once. */
    public const MAX_STUDENTS = 500;

    /** The card numbers, as one text. */
    public const MAX_CODES_LENGTH = 20000;

    protected array $digitFields = ['card_codes'];

    public function rules(): array
    {
        return [
            'course_id' => ['required', 'integer', 'exists:courses,id'],
            'attended_on' => ['required', 'date', 'before_or_equal:today'],
            'enrollment_ids' => ['required_without:card_codes', 'nullable', 'array', 'max:'.self::MAX_STUDENTS],
            'enrollment_ids.*' => ['integer', 'distinct'],
            'card_codes' => ['required_without:enrollment_ids', 'nullable', 'string', 'max:'.self::MAX_CODES_LENGTH],
        ];
    }

    public function messages(): array
    {
        return [
            'attended_on.before_or_equal' => 'لا يمكن تسجيل حضور بتاريخ في المستقبل.',
            'enrollment_ids.required_without' => 'اختر طالباً واحداً على الأقل، أو اكتب أرقام بطاقاتهم.',
            'card_codes.required_without' => 'اكتب أرقام البطاقات، أو اختر الطلاب بأسمائهم.',
        ];
    }

    public function attributes(): array
    {
        return [
            'course_id' => 'الدورة',
            'attended_on' => 'تاريخ الحضور',
            'enrollment_ids' => 'الطلاب',
            'enrollment_ids.*' => 'الطالب',
            'card_codes' => 'أرقام البطاقات',
        ];
    }
}
