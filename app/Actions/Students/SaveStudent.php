<?php

namespace App\Actions\Students;

use App\Enums\Section;
use App\Http\Requests\Students\StudentRequest;
use App\Models\Student;
use App\Services\Students\ProfileCompleteness;
use App\Services\Students\StudentPhotos;
use App\Support\Dates;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

/**
 * Creates a student, or updates one when $student is given, from the form
 * of a section: the student is then on that section's list. Each form
 * sends only its own fields (the mosque's has the tajweed level and the
 * pages memorized before, the English section's the English level), so
 * saving from one never clears what the other wrote.
 *
 * Recomputes both "profile complete" flags on every save: a phone number
 * removed in one section is missing in the other too.
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
    public function handle(array $input, ?Student $student = null, Section $section = Section::Mosque): Student
    {
        $data = StudentRequest::validateData($input);

        /** @var UploadedFile|null $photo */
        $photo = $data['photo'] ?? null;
        $removePhoto = (bool) ($data['remove_photo'] ?? false);
        unset($data['photo'], $data['remove_photo']);

        if (isset($data['birth_date'])) {
            $data['birth_date'] = Dates::day($data['birth_date']);
        }

        $student ??= $this->newStudentOf($section);
        $student->fill($data);
        $student->setAttribute($section->studentFlag(), true);

        foreach (Section::cases() as $each) {
            $student->setAttribute($each->profileFlag(), $this->completeness->isComplete($student->getAttributes(), $each));
        }

        $student->save();

        if ($photo !== null) {
            $this->changePhoto($student, $this->photos->keep($student, $photo));
        } elseif ($removePhoto) {
            $this->changePhoto($student, null);
        }

        return $student;
    }

    private function newStudentOf(Section $section): Student
    {
        $student = new Student;

        foreach (Section::cases() as $each) {
            $student->setAttribute($each->studentFlag(), $each === $section);
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
