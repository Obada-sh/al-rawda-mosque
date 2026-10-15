<?php

namespace App\Actions\Finance;

use App\Exceptions\BusinessRuleViolation;
use App\Models\Department;

/**
 * Deletes a department that has no expenses. One with expenses is
 * deactivated instead, so old expenses keep their department.
 */
final class DeleteDepartment
{
    /**
     * @throws BusinessRuleViolation department.has_expenses
     */
    public function handle(Department $department): void
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
