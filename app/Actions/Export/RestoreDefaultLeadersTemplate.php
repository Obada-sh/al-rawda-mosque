<?php

namespace App\Actions\Export;

use App\Services\Export\LeadersTemplates;

/**
 * Goes back to the default design of the leaders' slides; the admin's own
 * file is removed.
 */
final class RestoreDefaultLeadersTemplate
{
    public function __construct(private readonly LeadersTemplates $templates) {}

    public function handle(): void
    {
        $this->templates->restoreDefault();
    }
}
