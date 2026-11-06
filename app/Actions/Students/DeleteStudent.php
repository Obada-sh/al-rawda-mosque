<?php

namespace App\Actions\Students;

use App\Enums\Section;
use App\Exceptions\BusinessRuleViolation;
use App\Models\Student;
use App\Services\Students\StudentPhotos;

/**
 * Deletes a student from a section's list, when the student has no history
 * in that section (for example a duplicate entered by mistake).
 *
 * A student who is on the other section's list too is kept for it: only
 * this section's flag is cleared. Otherwise the student is deleted for
 * good, with the photo. A mosque student with history is archived instead;
 * an English one stays, with the courses they left.
 */
final class DeleteStudent
{
    public function __construct(private readonly StudentPhotos $photos) {}

    /**
     * @return bool true when the student was deleted, false when only taken off the section's list
     *
     * @throws BusinessRuleViolation student.has_records
     */
    public function handle(Student $student, Section $section = Section::Mosque): bool
    {
        if ($this->hasRecordsIn($student, $section)) {
            throw BusinessRuleViolation::because('student.has_records', match ($section) {
                Section::Mosque => 'لا يمكن حذف طالب له سجلات. يمكنك أرشفته بدلاً من ذلك.',
                Section::English => 'لا يمكن حذف طالب مسجَّل في دورة انجليزي.',
            });
        }

        $other = $section->other();

        if ($student->isIn($other) || $this->hasRecordsIn($student, $other)) {
            $student->forceFill([$section->studentFlag() => false])->save();

            return false;
        }

        $student->delete();

        $this->photos->remove($student->photo_path);

        return true;
    }

    private function hasRecordsIn(Student $student, Section $section): bool
    {
        return match ($section) {
            Section::Mosque => $student->enrollments()->exists() || $student->juzExams()->exists(),
            Section::English => $student->englishEnrollments()->exists(),
        };
    }
}
