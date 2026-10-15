<?php

namespace App\Actions\Points;

use App\Exceptions\BusinessRuleViolation;
use App\Models\PointTransaction;

final class DeleteManualPoints
{
    /**
     * @throws BusinessRuleViolation points.not_manual
     */
    public function handle(PointTransaction $transaction): void
    {
        if (! $transaction->isManual()) {
            throw BusinessRuleViolation::because(
                'points.not_manual',
                'النقاط التلقائية تُحذف بحذف الحضور أو التسميع أو النشاط الذي أضافها.',
            );
        }

        $transaction->delete();
    }
}
