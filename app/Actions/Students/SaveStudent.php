<?php

namespace App\Actions\Students;

use App\Http\Requests\Students\StudentRequest;
use App\Models\Student;
use App\Services\Students\ProfileCompleteness;
use App\Services\Students\StudentPhotos;
use App\Support\Dates;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

/**
 * Creates a student, or updates one when $student is given.
 * Recomputes the "profile complete" flag on every save.
 *
 * The photo is optional and has nothing to do with that flag. A new
 * picture replaces the one the student had; "remove_photo" takes it away.
 */
final class SaveStudent
{
    public function __construct(
        private readonly ProfileCompleteness $completeness,
        private readonly StudentPhotos $photos,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     *
     * @throws ValidationException
     */
    public function handle(array $input, ?Student $student = null): Student
    {
        $data = StudentRequest::validateData($input);

        /** @var UploadedFile|null $photo */
        $photo = $data['photo'] ?? null;
        $removePhoto = (bool) ($data['remove_photo'] ?? false);
        unset($data['photo'], $data['remove_photo']);

        if (isset($data['birth_date'])) {
            $data['birth_date'] = Dates::day($data['birth_date']);
        }

        $student ??= new Student;
        $student->fill($data);
        $student->is_profile_complete = $this->completeness->isComplete($student->getAttributes());
        $student->save();

        if ($photo !== null) {
            $this->changePhoto($student, $this->photos->keep($student, $photo));
        } elseif ($removePhoto) {
            $this->changePhoto($student, null);
        }

        return $student;
    }

    /**
     * The name is stored first and the old file removed last: whatever
     * goes wrong in between, the student never points at a missing file.
     */
    private function changePhoto(Student $student, ?string $name): void
    {
        $previous = $student->photo_path;

        $student->forceFill(['photo_path' => $name])->save();

        if ($previous !== $name) {
            $this->photos->remove($previous);
        }
    }
}
