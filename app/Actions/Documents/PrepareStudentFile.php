<?php

namespace App\Actions\Documents;

use App\Http\Requests\Documents\StudentFileRequest;
use App\Models\Student;
use App\Services\Statistics\StudentStandings;
use Illuminate\Validation\ValidationException;

/**
 * Builds a student's file: their details, and for each of their courses
 * (or the one chosen) the figures, every page recited and every session
 * attended or missed (see StudentFileBuilder).
 */
final class PrepareStudentFile
{
    public function __construct(
        private readonly StudentStandings $standings,
        private readonly StudentFileBuilder $builder,
    ) {}

    /**
     * @param  array<string, mixed>  $input  course_id (optional): one course of the student
     *
     * @throws ValidationException
     */
    public function handle(Student $student, array $input): StudentFile
    {
        $data = StudentFileRequest::validateData($input, ['student_id' => $student->getKey()]);

        return $this->builder->build(
            $student,
            isset($data['course_id']) ? (int) $data['course_id'] : null,
            $this->standings->forStudent($student),
        );
    }
}
