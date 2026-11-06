<?php

namespace App\Actions\English\Enrollments;

use App\Exceptions\BusinessRuleViolation;
use App\Models\English\EnglishEnrollment;

/**
 * Removes an enrollment made by mistake: one with no attendance, payment
 * or test result. Otherwise the date the student left is recorded instead.
 */
final class DeleteEnglishEnrollment
{
    /**
     * @throws BusinessRuleViolation english_enrollment.has_records
     */
    public function handle(EnglishEnrollment $enrollment): void
    {
        $hasRecords = $enrollment->attendances()->exists()
            || $enrollment->payments()->exists()
            || $enrollment->testResults()->exists();

        if ($hasRecords) {
            throw BusinessRuleViolation::because(
                'english_enrollment.has_records',
                'لا يمكن حذف تسجيل له حضور أو دفعات أو علامات. سجّل تاريخ خروج الطالب من الدورة بدلاً من ذلك.',
            );
        }

        $enrollment->delete();
    }
}
