<?php

namespace App\Services\Backup;

use Carbon\CarbonImmutable;

/**
 * How the evening copy for Google Drive went: the last copy put in the
 * folder, and the last attempt that failed after it.
 */
final readonly class DriveCopyState
{
    public function __construct(
        public ?CarbonImmutable $copiedAt = null,
        public ?string $copiedName = null,
        public ?CarbonImmutable $failedAt = null,
        public ?string $failure = null,
    ) {}

    public function hasFailed(): bool
    {
        return $this->failedAt !== null;
    }
}
