<?php

namespace App\Actions\Exams;

use App\Enums\ExamOrigin;
use App\Enums\ExamStatus;
use App\Exceptions\BusinessRuleViolation;
use App\Http\Requests\Exams\OpenJuzExamRequest;
use App\Models\Enrollment;
use App\Models\JuzExam;
use App\Models\Student;
use App\Support\Dates;
use Illuminate\Validation\ValidationException;

/**
 * Opens a juz exam by hand: for a juz the student memorized before joining
 * the system, or to retake an exam.
 */
final class OpenJuzExam
{
    /**
     * @param  array<string, mixed>  $input
     *
     * @throws ValidationException
     * @throws BusinessRuleViolation exam.enrollment_mismatch | exam.pending_exists
     */
    public function handle(array $input): JuzExam
    {
        $data = OpenJuzExamRequest::validateData($input);

        $student = Student::query()->findOrFail($data['student_id']);
        $enrollmentId = $data['enrollment_id'] ?? null;

        if ($enrollmentId !== null) {
            $belongsToStudent = Enrollment::query()
                ->whereKey($enrollmentId)
                ->where('student_id', $student->getKey())
                ->exists();

            if (! $belongsToStudent) {
                throw BusinessRuleViolation::because('exam.enrollment_mismatch', 'الدورة المختارة ليست من دورات هذا الطالب.');
            }
        }

        $hasPending = JuzExam::query()
            ->where('student_id', $student->getKey())
            ->where('juz_no', $data['juz_no'])
            ->where('status', ExamStatus::Pending->value)
            ->exists();

        if ($hasPending) {
            throw BusinessRuleViolation::because('exam.pending_exists', 'للطالب سبر منتظر لهذا الجزء.');
        }

        return JuzExam::query()->create([
            'student_id' => $student->getKey(),
            'juz_no' => (int) $data['juz_no'],
            'enrollment_id' => $enrollmentId,
            'status' => ExamStatus::Pending,
            'origin' => ExamOrigin::Manual,
            'triggered_on' => Dates::today()->toDateString(),
        ]);
    }
}
