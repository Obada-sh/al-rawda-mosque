<?php

namespace App\Actions\English\Enrollments;

use App\Actions\Students\SaveStudent;
use App\Enums\Section;
use App\Exceptions\BusinessRuleViolation;
use App\Models\English\EnglishEnrollment;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Registers a student who is not in the system yet, straight into an
 * English course: the student is created on the English section's list
 * and enrolled, both or neither. The photo is added later, from the
 * student's page.
 */
final class RegisterEnglishStudent
{
    /** The enrollment's fields; every other field of the input is the student's. */
    private const ENROLLMENT_FIELDS = ['english_course_id', 'english_hall_id', 'enrolled_on', 'price', 'price_note'];

    public function __construct(
        private readonly SaveStudent $saveStudent,
        private readonly EnrollEnglishStudent $enroll,
    ) {}

    /**
     * @param  array<string, mixed>  $input  the student's fields (StudentRequest) and the enrollment's (EnglishEnrollmentRequest)
     *
     * @throws ValidationException
     * @throws BusinessRuleViolation
     */
    public function handle(array $input): EnglishEnrollment
    {
        $student = Arr::except($input, [...self::ENROLLMENT_FIELDS, 'student_id', 'photo', 'remove_photo']);
        $enrollment = Arr::only($input, self::ENROLLMENT_FIELDS);

        return DB::transaction(fn (): EnglishEnrollment => $this->enroll->handle([
            ...$enrollment,
            'student_id' => $this->saveStudent->handle($student, section: Section::English)->getKey(),
        ]));
    }
}
