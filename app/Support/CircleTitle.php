<?php

namespace App\Support;

/**
 * How a circle is called in a sentence: "حلقة الفرقان". A circle whose
 * name already says it ("حلقة الفرقان") is not called "حلقة حلقة الفرقان".
 */
final class CircleTitle
{
    private const WORD = 'حلقة';

    public static function of(string $name): string
    {
        $name = trim($name);

        return str_starts_with($name, self::WORD) ? $name : self::WORD.' '.$name;
    }
}
