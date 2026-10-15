<?php

namespace App\Http\Requests\Exams;

use App\Http\Requests\BaseRequest;
use App\Support\QuranPages;

/**
 * Opening an exam by hand, for a juz memorized before joining the system
 * or for a retake.
 */
class OpenJuzExamRequest extends BaseRequest
{
    protected array $digitFields = ['juz_no'];

    public function rules(): array
    {
        return [
            'student_id' => ['required', 'integer', 'exists:students,id'],
            'juz_no' => ['required', 'integer', 'min:1', 'max:'.QuranPages::JUZ_COUNT],
            'enrollment_id' => ['nullable', 'integer', 'exists:enrollments,id'],
        ];
    }

    public function attributes(): array
    {
        return [
            'student_id' => 'الطالب',
            'juz_no' => 'رقم الجزء',
            'enrollment_id' => 'الدورة',
        ];
    }
}
