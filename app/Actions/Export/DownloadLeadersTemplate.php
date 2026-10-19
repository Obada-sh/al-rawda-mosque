<?php

namespace App\Actions\Export;

use App\Exceptions\BusinessRuleViolation;
use App\Services\Export\ExportedFile;
use App\Services\Export\LeadersTemplates;
use RuntimeException;

/**
 * Hands the admin a slide template to open in PowerPoint: the default
 * design to start their own from, or the design they uploaded earlier.
 */
final class DownloadLeadersTemplate
{
    public const NO_CUSTOM_TEMPLATE = 'slide_template.not_uploaded';

    private const MIME_TYPE = 'application/vnd.openxmlformats-officedocument.presentationml.presentation';

    public function __construct(private readonly LeadersTemplates $templates) {}

    /**
     * The default design.
     */
    public function default(): ExportedFile
    {
        $contents = @file_get_contents($this->templates->defaultPath());

        if ($contents === false) {
            throw new RuntimeException('The default slide template is missing.');
        }

        return new ExportedFile('قالب شرائح الأوائل.pptx', $contents, self::MIME_TYPE);
    }

    /**
     * The design the admin uploaded.
     *
     * @throws BusinessRuleViolation slide_template.not_uploaded, when the default design is in use
     */
    public function custom(): ExportedFile
    {
        $contents = $this->templates->customContents();

        if ($contents === null) {
            throw BusinessRuleViolation::because(self::NO_CUSTOM_TEMPLATE, 'لا يوجد قالب خاص: التصميم الافتراضي هو المستعمل.');
        }

        return new ExportedFile('قالب شرائح الأوائل - قالبي.pptx', $contents, self::MIME_TYPE);
    }
}
