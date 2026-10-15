<?php

namespace App\Services\Export\Pptx;

use RuntimeException;

/**
 * A file that cannot serve as a slide template. The reason is a stable
 * key; the screens turn it into a message for the admin.
 */
final class InvalidSlideTemplate extends RuntimeException
{
    public const NOT_A_PRESENTATION = 'not_a_presentation';

    public const TOO_LARGE = 'too_large';

    public const NO_PLACEHOLDERS = 'no_placeholders';

    private function __construct(public readonly string $reason, string $message)
    {
        parent::__construct($message);
    }

    public static function notAPresentation(): self
    {
        return new self(self::NOT_A_PRESENTATION, 'The file is not a PowerPoint presentation (.pptx).');
    }

    public static function tooLarge(): self
    {
        return new self(self::TOO_LARGE, 'The presentation is too large to be a template.');
    }

    public static function noPlaceholders(): self
    {
        return new self(self::NO_PLACEHOLDERS, 'No slide of the presentation contains a placeholder such as {{circle}}.');
    }
}
