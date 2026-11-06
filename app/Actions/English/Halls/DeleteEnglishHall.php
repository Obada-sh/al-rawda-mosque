<?php

namespace App\Actions\English\Halls;

use App\Exceptions\BusinessRuleViolation;
use App\Models\English\EnglishHall;

/**
 * Deletes a hall that no student was ever placed in. A hall that was used
 * is deactivated instead.
 */
final class DeleteEnglishHall
{
    /**
     * @throws BusinessRuleViolation english_hall.has_students
     */
    public function handle(EnglishHall $hall): void
    {
        if ($hall->enrollments()->exists()) {
            throw BusinessRuleViolation::because(
                'english_hall.has_students',
                'لا يمكن حذف قاعة فيها طلاب. يمكنك تعطيلها بدلاً من ذلك.',
            );
        }

        $hall->delete();
    }
}
