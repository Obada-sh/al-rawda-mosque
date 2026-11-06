<?php

namespace App\Actions\English\Teachers;

use App\Http\Requests\English\EnglishTeacherRequest;
use App\Models\English\EnglishTeacher;
use Illuminate\Validation\ValidationException;

/**
 * Adds a teacher to the English section, or updates one.
 */
final class SaveEnglishTeacher
{
    /**
     * @param  array<string, mixed>  $input
     *
     * @throws ValidationException
     */
    public function handle(array $input, ?EnglishTeacher $teacher = null): EnglishTeacher
    {
        $data = EnglishTeacherRequest::validateData($input);

        $teacher ??= new EnglishTeacher;
        $teacher->fill($data)->save();

        return $teacher;
    }
}
