<?php

namespace App\Actions\English\Enrollments;

use App\Exceptions\BusinessRuleViolation;
use App\Http\Requests\English\EnglishHallChangeRequest;
use App\Models\English\EnglishEnrollment;
use App\Models\English\EnglishHall;
use Illuminate\Validation\ValidationException;

/**
 * Moves a student to another hall of the same course. Everything the
 * student has in the course (attendance, payments, results) stays theirs.
 */
final class ChangeEnglishHall
{
    /**
     * @param  array<string, mixed>  $input
     *
     * @throws ValidationException
     * @throws BusinessRuleViolation english_enrollment.left | english_hall.other_course | english_hall.inactive | english_hall.same
     */
    public function handle(EnglishEnrollment $enrollment, array $input): EnglishEnrollment
    {
        $data = EnglishHallChangeRequest::validateData($input);
        $hall = EnglishHall::query()->findOrFail($data['english_hall_id']);

        if ($enrollment->hasLeft()) {
            throw BusinessRuleViolation::because('english_enrollment.left', 'الطالب خرج من الدورة.');
        }

        if ((int) $hall->english_course_id !== (int) $enrollment->english_course_id) {
            throw BusinessRuleViolation::because('english_hall.other_course', 'القاعة المختارة لا تتبع هذه الدورة.');
        }

        if ((int) $hall->getKey() === (int) $enrollment->english_hall_id) {
            throw BusinessRuleViolation::because('english_hall.same', 'الطالب في هذه القاعة أصلاً.');
        }

        if (! $hall->is_active) {
            throw BusinessRuleViolation::because('english_hall.inactive', 'القاعة المختارة غير نشطة.');
        }

        $enrollment->fill(['english_hall_id' => $hall->getKey()])->save();

        return $enrollment;
    }
}
