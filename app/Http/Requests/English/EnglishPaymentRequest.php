<?php

namespace App\Http\Requests\English;

use App\Http\Requests\BaseRequest;

/**
 * Money a student paid for an English course: an amount in whole Syrian
 * pounds, on a day.
 */
class EnglishPaymentRequest extends BaseRequest
{
    protected array $digitFields = ['amount'];

    public function rules(): array
    {
        return [
            'english_enrollment_id' => ['required', 'integer', 'exists:english_enrollments,id'],
            'amount' => ['required', 'integer', 'min:1', 'max:'.EnglishCourseRequest::MAX_PRICE],
            'paid_on' => ['required', 'date', 'before_or_equal:today'],
            'notes' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'amount.integer' => 'المبلغ رقم صحيح بالليرة السورية، بلا فواصل.',
            'amount.min' => 'المبلغ يجب أن يكون أكبر من صفر.',
            'paid_on.before_or_equal' => 'تاريخ الدفع لا يمكن أن يكون في المستقبل.',
        ];
    }

    public function attributes(): array
    {
        return [
            'english_enrollment_id' => 'الطالب',
            'amount' => 'المبلغ',
            'paid_on' => 'تاريخ الدفع',
            'notes' => 'ملاحظات',
        ];
    }
}
