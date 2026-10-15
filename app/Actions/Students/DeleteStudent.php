<?php

namespace App\Actions\Students;

use App\Exceptions\BusinessRuleViolation;
use App\Models\Student;
use App\Services\Students\StudentPhotos;

/**
 * Permanently deletes a student who has no history at all (for example a
 * duplicate entered by mistake). A student with history is archived instead.
 */
final class DeleteStudent
{
    public function __construct(private readonly StudentPhotos $photos) {}

    /**
     * @throws BusinessRuleViolation student.has_records
     */
    public function handle(Student $student): void
    {
        if ($student->enrollments()->exists() || $student->juzExams()->exists()) {
            throw BusinessRuleViolation::because(
                'student.has_records',
                'لا يمكن حذف طالب له سجلات. يمكنك أرشفته بدلاً من ذلك.',
            );
        }

        $student->delete();

        $this->photos->remove($student->photo_path);
    }
}
