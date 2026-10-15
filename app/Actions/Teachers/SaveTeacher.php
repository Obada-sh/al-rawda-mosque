<?php

namespace App\Actions\Teachers;

use App\Http\Requests\Teachers\TeacherRequest;
use App\Models\Teacher;
use App\Services\Teachers\TeacherPhotos;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

/**
 * Creates a teacher, or updates one when $teacher is given.
 *
 * The photo is optional; a new picture replaces the one the teacher had,
 * "remove_photo" takes it away. (A teacher's attendance cards belong to
 * the courses: see Services\Teachers\TeacherCards.)
 */
final class SaveTeacher
{
    public function __construct(private readonly TeacherPhotos $photos) {}

    /**
     * @param  array<string, mixed>  $input
     *
     * @throws ValidationException
     */
    public function handle(array $input, ?Teacher $teacher = null): Teacher
    {
        $data = TeacherRequest::validateData($input);

        /** @var UploadedFile|null $photo */
        $photo = $data['photo'] ?? null;
        $removePhoto = (bool) ($data['remove_photo'] ?? false);
        unset($data['photo'], $data['remove_photo']);

        $teacher ??= new Teacher;
        $teacher->fill($data)->save();

        if ($photo !== null) {
            $this->changePhoto($teacher, $this->photos->keep($teacher, $photo));
        } elseif ($removePhoto) {
            $this->changePhoto($teacher, null);
        }

        return $teacher;
    }

    private function changePhoto(Teacher $teacher, ?string $name): void
    {
        $previous = $teacher->photo_path;

        $teacher->forceFill(['photo_path' => $name])->save();

        if ($previous !== $name) {
            $this->photos->remove($previous);
        }
    }
}
