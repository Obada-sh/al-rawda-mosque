<?php

namespace App\Actions\Export;

use App\Exceptions\BusinessRuleViolation;
use App\Http\Requests\Export\SlideTemplateRequest;
use App\Services\Export\LeadersTemplates;
use App\Services\Export\Pptx\InvalidSlideTemplate;
use App\Services\Export\Pptx\SlideDeckTemplate;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

/**
 * Replaces the default design of the leaders' slides by a PowerPoint file
 * of the admin. The file is opened and checked before it is accepted, so
 * a file that cannot give slides never becomes the template.
 */
final class SaveLeadersTemplate
{
    public function __construct(private readonly LeadersTemplates $templates) {}

    /**
     * @param  array<string, mixed>  $input  'template' => the uploaded .pptx file
     * @return int how many leaders the new template has room for
     *
     * @throws ValidationException
     * @throws BusinessRuleViolation slide_template.not_a_presentation | slide_template.too_large | slide_template.no_placeholders | slide_template.no_slots
     */
    public function handle(array $input): int
    {
        /** @var UploadedFile $file */
        $file = SlideTemplateRequest::validateData($input)['template'];

        try {
            $slots = SlideDeckTemplate::open($file->getRealPath())->slots();
        } catch (InvalidSlideTemplate $exception) {
            throw SlideTemplateViolation::from($exception);
        }

        if ($slots < 1) {
            throw SlideTemplateViolation::withoutSlots();
        }

        $this->templates->replaceWith($file->getRealPath());

        return $slots;
    }
}
