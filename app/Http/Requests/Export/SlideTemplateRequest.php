<?php

namespace App\Http\Requests\Export;

use App\Http\Requests\BaseRequest;

/**
 * A PowerPoint file uploaded to serve as the slide template.
 *
 * Only the envelope is checked here; what is inside the file is checked
 * by opening it as a presentation.
 */
class SlideTemplateRequest extends BaseRequest
{
    public const MAX_KILOBYTES = 10240;

    public function rules(): array
    {
        return [
            'template' => ['required', 'file', 'extensions:pptx', 'max:'.self::MAX_KILOBYTES],
        ];
    }

    public function messages(): array
    {
        return [
            'template.extensions' => 'اختر ملف PowerPoint بصيغة ‎.pptx.',
            'template.max' => 'حجم القالب لا يتجاوز 10 ميغابايت.',
        ];
    }

    public function attributes(): array
    {
        return [
            'template' => 'ملف القالب',
        ];
    }
}
