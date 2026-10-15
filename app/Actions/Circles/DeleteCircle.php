<?php

namespace App\Actions\Circles;

use App\Exceptions\BusinessRuleViolation;
use App\Models\Circle;
use App\Services\Circles\CircleLogos;

final class DeleteCircle
{
    public function __construct(private readonly CircleLogos $logos) {}

    /**
     * @throws BusinessRuleViolation circle.has_records
     */
    public function handle(Circle $circle): void
    {
        $isUsed = $circle->enrollments()->exists()
            || $circle->incomingTransfers()->exists()
            || $circle->outgoingTransfers()->exists()
            || $circle->activities()->exists();

        if ($isUsed) {
            throw BusinessRuleViolation::because(
                'circle.has_records',
                'لا يمكن حذف حلقة فيها طلاب أو لها سجل تنقلات أو نشاطات. يمكنك تعطيلها بدلاً من ذلك.',
            );
        }

        $circle->delete();

        $this->logos->remove($circle->logo_path);
    }
}
