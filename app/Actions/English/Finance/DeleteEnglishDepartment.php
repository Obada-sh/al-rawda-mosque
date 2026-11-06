<?php

namespace App\Actions\English\Finance;

use App\Exceptions\BusinessRuleViolation;
use App\Models\English\EnglishDepartment;

/**
 * Deletes a department of the English section that has no expenses. One
 * with expenses is deactivated instead, so old expenses keep their department.
 */
final class DeleteEnglishDepartment
{
    /**
     * @throws BusinessRuleViolation department.has_expenses
     */
    public function handle(EnglishDepartment $department): void
    {
        if ($department->expenses()->exists()) {
            throw BusinessRuleViolation::because(
                'department.has_expenses',
                'لا يمكن حذف قسم له مصاريف. يمكنك تعطيله بدلاً من ذلك.',
            );
        }

        $department->delete();
    }
}
