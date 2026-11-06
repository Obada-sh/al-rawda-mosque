<?php

namespace App\Actions\English\Enrollments;

use App\Contracts\CardCodeGenerator;
use App\Enums\Section;
use App\Exceptions\BusinessRuleViolation;
use App\Http\Requests\English\EnglishEnrollmentRequest;
use App\Models\English\EnglishCourse;
use App\Models\English\EnglishEnrollment;
use App\Models\English\EnglishHall;
use App\Models\Student;
use App\Services\Students\SectionMembership;
use App\Support\Dates;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Enrolls a student in an English course, in the hall the admin picked,
 * at the course's price or at a price of their own, and issues their
 * attendance card code.
 *
 * Any student may be enrolled: one of the mosque is put on the English
 * section's list too, and keeps everything the mosque knows of them.
 */
final class EnrollEnglishStudent
{
    public function __construct(
        private readonly CardCodeGenerator $cardCodes,
        private readonly SectionMembership $sections,
    ) {}

    /**
     * @param  array<string, mixed>  $input  see EnglishEnrollmentRequest
     *
     * @throws ValidationException
     * @throws BusinessRuleViolation english_course.finished | english_hall.other_course
     *                               | english_hall.inactive | english_enrollment.duplicate
     */
    public function handle(array $input): EnglishEnrollment
    {
        $data = EnglishEnrollmentRequest::validateData($input);

        $student = Student::query()->findOrFail($data['student_id']);
        $course = EnglishCourse::query()->findOrFail($data['english_course_id']);
        $hall = EnglishHall::query()->findOrFail($data['english_hall_id']);

        if (! $course->isActive()) {
            throw BusinessRuleViolation::because('english_course.finished', 'لا يمكن التسجيل في دورة منتهية.');
        }

        if ((int) $hall->english_course_id !== (int) $course->getKey()) {
            throw BusinessRuleViolation::because('english_hall.other_course', 'القاعة المختارة لا تتبع هذه الدورة.');
        }

        if (! $hall->is_active) {
            throw BusinessRuleViolation::because('english_hall.inactive', 'القاعة المختارة غير نشطة.');
        }

        return DB::transaction(function () use ($student, $course, $hall, $data): EnglishEnrollment {
            $alreadyEnrolled = EnglishEnrollment::query()
                ->where('student_id', $student->getKey())
                ->where('english_course_id', $course->getKey())
                ->lockForUpdate()
                ->exists();

            if ($alreadyEnrolled) {
                throw BusinessRuleViolation::because('english_enrollment.duplicate', 'الطالب مسجَّل مسبقاً في هذه الدورة.');
            }

            $this->sections->join($student, Section::English, (int) $course->english_level_id);

            $price = isset($data['price']) ? (int) $data['price'] : null;

            return EnglishEnrollment::query()->create([
                'student_id' => $student->getKey(),
                'english_course_id' => $course->getKey(),
                'english_hall_id' => $hall->getKey(),
                'card_code' => $this->cardCodes->generate(),
                'enrolled_on' => Dates::day($data['enrolled_on']),
                'price' => $price,
                'price_note' => $price === null ? null : $data['price_note'],
            ]);
        });
    }
}
