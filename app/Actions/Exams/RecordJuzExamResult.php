<?php

namespace App\Actions\Exams;

use App\Enums\ExamStatus;
use App\Exceptions\BusinessRuleViolation;
use App\Http\Requests\Exams\JuzExamResultRequest;
use App\Models\JuzExam;
use App\Models\Teacher;
use App\Support\Dates;
use Illuminate\Validation\ValidationException;

/**
 * Stores the result of an exam (date, examiner, grade) and marks it done.
 * Saving again corrects a result entered by mistake.
 */
final class RecordJuzExamResult
{
    /**
     * @param  array<string, mixed>  $input
     *
     * @throws ValidationException
     * @throws BusinessRuleViolation teacher.inactive
     */
    public function handle(JuzExam $exam, array $input): JuzExam
    {
        $data = JuzExamResultRequest::validateData($input);

        $examinerChanged = (int) $data['examiner_teacher_id'] !== (int) $exam->examiner_teacher_id;

        if ($examinerChanged && ! Teacher::query()->whereKey($data['examiner_teacher_id'])->where('is_active', true)->exists()) {
            throw BusinessRuleViolation::because('teacher.inactive', 'الأستاذ المختار غير نشط.');
        }

        $exam->fill([
            'exam_date' => Dates::day($data['exam_date']),
            'examiner_teacher_id' => $data['examiner_teacher_id'],
            'grade' => $data['grade'],
            'notes' => $data['notes'] ?? null,
            'status' => ExamStatus::Done,
        ])->save();

        return $exam;
    }
}
