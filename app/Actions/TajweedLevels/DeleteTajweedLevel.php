<?php

namespace App\Actions\TajweedLevels;

use App\Exceptions\BusinessRuleViolation;
use App\Models\TajweedLevel;

final class DeleteTajweedLevel
{
    /**
     * @throws BusinessRuleViolation tajweed_level.in_use
     */
    public function handle(TajweedLevel $level): void
    {
        if ($level->students()->exists()) {
            throw BusinessRuleViolation::because(
                'tajweed_level.in_use',
                'لا يمكن حذف مستوى مرتبط بطلاب.',
            );
        }

        $level->delete();
    }
}
