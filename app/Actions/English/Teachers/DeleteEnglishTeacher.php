<?php

namespace App\Actions\English\Teachers;

use App\Exceptions\BusinessRuleViolation;
use App\Models\English\EnglishTeacher;

/**
 * Deletes a teacher entered by mistake: one who is the teacher or the
 * assistant of no hall. A teacher who taught is deactivated instead.
 */
final class DeleteEnglishTeacher
{
    /**
     * @throws BusinessRuleViolation english_teacher.in_use
     */
    public function handle(EnglishTeacher $teacher): void
    {
        if ($teacher->halls()->exists() || $teacher->assistedHalls()->exists()) {
            throw BusinessRuleViolation::because(
                'english_teacher.in_use',
                'لا يمكن حذف أستاذ مرتبط بقاعة. يمكنك تعطيله بدلاً من ذلك.',
            );
        }

        $teacher->delete();
    }
}
