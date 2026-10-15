<?php

namespace App\Actions\Enrollments;

use App\Exceptions\BusinessRuleViolation;
use App\Models\Enrollment;

/**
 * Removes an enrollment made by mistake. An enrollment that already has
 * attendance, recitations, points, exams, activities or purchases cannot be removed:
 * record the date the student left instead.
 */
final class DeleteEnrollment
{
    /**
     * @throws BusinessRuleViolation enrollment.has_records
     */
    public function handle(Enrollment $enrollment): void
    {
        $hasRecords = $enrollment->attendances()->exists()
            || $enrollment->recitations()->exists()
            || $enrollment->pointTransactions()->exists()
            || $enrollment->juzExams()->exists()
            || $enrollment->activityAttendances()->exists()
            || $enrollment->marketSales()->exists();

        if ($hasRecords) {
            throw BusinessRuleViolation::because(
                'enrollment.has_records',
                'لا يمكن حذف تسجيل له سجلات. سجّل تاريخ خروج الطالب من الدورة بدلاً من ذلك.',
            );
        }

        // The transfers log of the enrollment is removed with it (cascade).
        $enrollment->delete();
    }
}
