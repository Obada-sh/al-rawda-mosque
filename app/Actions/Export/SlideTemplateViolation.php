<?php

namespace App\Actions\Export;

use App\Exceptions\BusinessRuleViolation;
use App\Services\Export\Pptx\InvalidSlideTemplate;

/**
 * Says in Arabic why a file cannot serve as a slide template.
 */
final class SlideTemplateViolation
{
    public const NOT_A_PRESENTATION = 'slide_template.not_a_presentation';

    public const TOO_LARGE = 'slide_template.too_large';

    public const NO_PLACEHOLDERS = 'slide_template.no_placeholders';

    public const NO_SLOTS = 'slide_template.no_slots';

    public static function from(InvalidSlideTemplate $exception): BusinessRuleViolation
    {
        return match ($exception->reason) {
            InvalidSlideTemplate::TOO_LARGE => BusinessRuleViolation::because(
                self::TOO_LARGE,
                'ملف القالب أكبر من أن يُستعمل قالباً للشرائح.',
            ),
            InvalidSlideTemplate::NO_PLACEHOLDERS => BusinessRuleViolation::because(
                self::NO_PLACEHOLDERS,
                'لا توجد في القالب شريحة فيها خانات للتعبئة مثل {{circle}} و {{name1}}.',
            ),
            default => BusinessRuleViolation::because(
                self::NOT_A_PRESENTATION,
                'الملف ليس عرض PowerPoint صالحاً بصيغة ‎.pptx.',
            ),
        };
    }

    /**
     * A template that names no leader ({{name1}}, {{score1}}...) would
     * give slides without any student on them.
     */
    public static function withoutSlots(): BusinessRuleViolation
    {
        return BusinessRuleViolation::because(
            self::NO_SLOTS,
            'القالب لا يحتوي على خانات الأوائل مثل {{name1}} و {{score1}}.',
        );
    }
}
