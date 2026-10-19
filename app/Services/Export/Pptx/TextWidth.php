<?php

namespace App\Services\Export\Pptx;

/**
 * How wide a line of text is drawn, without the font at hand.
 *
 * The slides are drawn by PowerPoint on the mosque's computer, with
 * whatever font the template names, so the width can only be estimated.
 * The estimate leans on the wide side for the Arabic fonts in use (a
 * joined Arabic letter is about half as wide as the font size), so that
 * a text said to fit does fit.
 */
final class TextWidth
{
    private const ARABIC_LETTER = 0.55;

    private const LATIN_LETTER = 0.6;

    private const CAPITAL_LETTER = 0.7;

    private const DIGIT = 0.62;

    private const SPACE = 0.28;

    private const OTHER = 0.45;

    /**
     * The width of the text in ems: 1 is as wide as the font size.
     */
    public static function ems(string $text): float
    {
        $width = 0.0;

        foreach (mb_str_split($text) as $character) {
            $width += match (true) {
                // Short vowels and other marks sit on the letters and take no room of their own.
                preg_match('/\p{Mn}|\p{Cf}/u', $character) === 1 => 0.0,
                $character === ' ' || $character === "\u{00A0}" => self::SPACE,
                preg_match('/\p{Nd}/u', $character) === 1 => self::DIGIT,
                preg_match('/\p{Arabic}/u', $character) === 1 => self::ARABIC_LETTER,
                preg_match('/\p{Lu}/u', $character) === 1 => self::CAPITAL_LETTER,
                preg_match('/\p{L}/u', $character) === 1 => self::LATIN_LETTER,
                default => self::OTHER,
            };
        }

        return $width;
    }
}
