<?php

namespace App\Http\Requests\Export;

use App\Http\Requests\BaseRequest;
use App\Services\Export\LeadersTemplates;

/**
 * Which default design the admin wants a copy of: the one for that many
 * leaders on a slide.
 */
class DefaultSlideTemplateRequest extends BaseRequest
{
    protected array $digitFields = ['leaders'];

    public function rules(): array
    {
        return [
            'leaders' => ['required', 'integer', 'min:1', 'max:'.LeadersTemplates::MAX_LEADERS],
        ];
    }

    public function attributes(): array
    {
        return [
            'leaders' => 'عدد الأوائل',
        ];
    }
}
