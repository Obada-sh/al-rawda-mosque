<?php

namespace App\Actions\Enrollments;

use App\Contracts\CardCodeGenerator;
use App\Exceptions\BusinessRuleViolation;
use App\Http\Requests\Enrollments\EnrollmentRequest;
use App\Models\Circle;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Student;
use App\Support\Dates;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Enrolls a student in a course, in the circle the admin picked, and
 * issues their attendance card code.
 */
final class EnrollStudent
{
    public function __construct(private readonly CardCodeGenerator $cardCodes) {}

    /**
     * @param  array<string, mixed>  $input
     *
     * @throws ValidationException
     * @throws BusinessRuleViolation student.archived | course.finished | circle.other_course
     *                               | circle.inactive | enrollment.duplicate
     */
    public function handle(array $input): Enrollment
    {
        $data = EnrollmentRequest::validateData($input);

        $student = Student::query()->findOrFail($data['student_id']);
        $course = Course::query()->findOrFail($data['course_id']);
        $circle = Circle::query()->findOrFail($data['circle_id']);

        if ($student->isArchived()) {
            throw BusinessRuleViolation::because('student.archived', 'الطالب مؤرشف. أعد تفعيله قبل تسجيله في دورة.');
        }

        if (! $course->isActive()) {
            throw BusinessRuleViolation::because('course.finished', 'لا يمكن التسجيل في دورة منتهية.');
        }

        if ((int) $circle->course_id !== (int) $course->getKey()) {
            throw BusinessRuleViolation::because('circle.other_course', 'الحلقة المختارة لا تتبع هذه الدورة.');
        }

        if (! $circle->is_active) {
            throw BusinessRuleViolation::because('circle.inactive', 'الحلقة المختارة غير نشطة.');
        }

        return DB::transaction(function () use ($student, $course, $circle, $data): Enrollment {
            $alreadyEnrolled = Enrollment::query()
                ->where('student_id', $student->getKey())
                ->where('course_id', $course->getKey())
                ->lockForUpdate()
                ->exists();

            if ($alreadyEnrolled) {
                throw BusinessRuleViolation::because('enrollment.duplicate', 'الطالب مسجَّل مسبقاً في هذه الدورة.');
            }

            return Enrollment::query()->create([
                'student_id' => $student->getKey(),
                'course_id' => $course->getKey(),
                'circle_id' => $circle->getKey(),
                'card_code' => $this->cardCodes->generate(),
                'enrolled_on' => Dates::day($data['enrolled_on']),
            ]);
        });
    }
}
