<?php

namespace App\Actions\English\Enrollments;

use App\Exceptions\BusinessRuleViolation;
use App\Http\Requests\English\EnglishPriceRequest;
use App\Models\English\EnglishCourse;
use App\Models\English\EnglishEnrollment;
use App\Models\English\EnglishPayment;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Gives an enrolled student a price of their own for the course (a
 * discount, with its reason), changes it, or takes it away so the student
 * pays the course's price. The student must never have paid more than
 * the new price.
 */
final class ChangeEnglishPrice
{
    /**
     * @param  array<string, mixed>  $input  price (null: the course's price), price_note
     *
     * @throws ValidationException
     * @throws BusinessRuleViolation english_enrollment.price_below_paid
     */
    public function handle(EnglishEnrollment $enrollment, array $input): EnglishEnrollment
    {
        $data = EnglishPriceRequest::validateData($input);
        $price = isset($data['price']) ? (int) $data['price'] : null;

        return DB::transaction(function () use ($enrollment, $data, $price): EnglishEnrollment {
            // The same locks as a payment, in the same order: neither can slip past the other.
            $course = EnglishCourse::query()->lockForUpdate()->findOrFail($enrollment->english_course_id);
            $enrollment = EnglishEnrollment::query()->lockForUpdate()->findOrFail($enrollment->getKey());

            $due = $price ?? $course->price;
            $paid = (int) EnglishPayment::query()->where('english_enrollment_id', $enrollment->getKey())->sum('amount');

            if ($paid > $due) {
                throw BusinessRuleViolation::because(
                    'english_enrollment.price_below_paid',
                    'دفع الطالب '.Money::pounds($paid).'، وهو أكثر من هذا السعر.',
                );
            }

            $enrollment->fill([
                'price' => $price,
                'price_note' => $price === null ? null : $data['price_note'],
            ])->save();

            return $enrollment->setRelation('course', $course);
        });
    }
}
