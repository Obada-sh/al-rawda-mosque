<?php

namespace App\Actions\Teachers;

use App\Exceptions\BusinessRuleViolation;
use App\Models\Teacher;
use App\Services\Teachers\TeacherPhotos;

/**
 * Deletes a teacher who was never used. A teacher who appears in any
 * record is deactivated instead, so old records keep their teacher.
 */
final class DeleteTeacher
{
    public function __construct(private readonly TeacherPhotos $photos) {}

    /**
     * @throws BusinessRuleViolation teacher.has_records
     */
    public function handle(Teacher $teacher): void
    {
        $isUsed = $teacher->supervisedCircles()->exists()
            || $teacher->assistedCircles()->exists()
            || $teacher->recitations()->exists()
            || $teacher->examinedExams()->exists()
            || $teacher->attendances()->exists();

        if ($isUsed) {
            throw BusinessRuleViolation::because(
                'teacher.has_records',
                'لا يمكن حذف أستاذ له سجلات. يمكنك تعطيله بدلاً من ذلك.',
            );
        }

        $teacher->delete();

        $this->photos->remove($teacher->photo_path);
    }
}
