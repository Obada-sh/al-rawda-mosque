<?php

namespace App\Actions\English\Payments;

use App\Exceptions\BusinessRuleViolation;
use App\Http\Requests\English\EnglishPaymentRequest;
use App\Models\English\EnglishCourse;
use App\Models\English\EnglishEnrollment;
use App\Models\English\EnglishPayment;
use App\Support\Dates;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Records money a student paid for an English course ("طالب دفع 50,000
 * ل.س عن دورة A1"), or corrects a payment. A student never pays more than
 * what remains of their price: the payment is refused and the remainder
 * is said.
 */
final class RecordEnglishPayment
{
    /**
     * @param  array<string, mixed>  $input  see EnglishPaymentRequest; a corrected payment keeps its student
     *
     * @throws ValidationException
     * @throws BusinessRuleViolation english_payment.more_than_remaining | english_payment.other_enrollment
     */
    public function handle(array $input, ?EnglishPayment $payment = null): EnglishPayment
    {
        $data = EnglishPaymentRequest::validateData($input);
        $amount = (int) $data['amount'];

        if ($payment !== null && (int) $data['english_enrollment_id'] !== (int) $payment->english_enrollment_id) {
            throw BusinessRuleViolation::because('english_payment.other_enrollment', 'لا يمكن نقل دفعة إلى طالب آخر. احذفها وسجّلها للطالب الصحيح.');
        }

        return DB::transaction(function () use ($data, $amount, $payment): EnglishPayment {
            // The course first, then the student: the same order as a change of
            // price, so neither can slip past the other.
            $enrollment = EnglishEnrollment::query()->findOrFail($data['english_enrollment_id']);
            $course = EnglishCourse::query()->lockForUpdate()->findOrFail($enrollment->english_course_id);
            $enrollment = EnglishEnrollment::query()->lockForUpdate()->findOrFail($enrollment->getKey());

            $paidBefore = (int) EnglishPayment::query()
                ->where('english_enrollment_id', $enrollment->getKey())
                ->when($payment !== null, fn ($query) => $query->whereKeyNot($payment->getKey()))
                ->sum('amount');
            $remaining = max(0, ($enrollment->price ?? $course->price) - $paidBefore);

            if ($amount > $remaining) {
                throw BusinessRuleViolation::because(
                    'english_payment.more_than_remaining',
                    $remaining === 0
                        ? 'دفع الطالب سعر الدورة كاملاً، فلا يبقى عليه شيء.'
                        : 'المتبقي على الطالب '.Money::pounds($remaining).' فقط.',
                );
            }

            $payment ??= new EnglishPayment(['english_enrollment_id' => $enrollment->getKey()]);
            $payment->fill([
                'amount' => $amount,
                'paid_on' => Dates::day($data['paid_on']),
                'notes' => $data['notes'] ?? null,
            ])->save();

            return $payment;
        });
    }
}
