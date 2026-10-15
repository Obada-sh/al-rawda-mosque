<?php

namespace App\Support;

use Illuminate\Support\HtmlString;

/**
 * The font of the attendance card: the designer's GE Dinar One Medium.
 *
 * That font is not free, so it is not something the system can ship by
 * itself: the mosque copies its own file into public/fonts/card/ (named
 * card.woff2, card.woff, card.ttf or card.otf). A computer that has the
 * font installed uses it even without the file. Anywhere else the card
 * falls back on the panel's font, in the same places and colours.
 */
final class CardFont
{
    public const FAMILY = 'Rawda Card';

    public const FALLBACK = "'IBM Plex Sans Arabic', 'Segoe UI', Tahoma, sans-serif";

    /** Names the font is known by when it is installed on the computer. */
    private const INSTALLED_AS = ['GE Dinar One Medium', 'GEDinarOne-Medium', 'GE Dinar One'];

    /** @var array<string, string> extension => CSS format */
    private const FILES = ['woff2' => 'woff2', 'woff' => 'woff', 'ttf' => 'truetype', 'otf' => 'opentype'];

    /**
     * The @font-face rule of the card's font.
     */
    public static function faceCss(): string
    {
        $sources = array_map(fn (string $name): string => "local('{$name}')", self::INSTALLED_AS);

        if (($file = self::file()) !== null) {
            $sources[] = "url('".asset('fonts/card/'.$file)."') format('".self::FILES[pathinfo($file, PATHINFO_EXTENSION)]."')";
        }

        return "@font-face { font-family: '".self::FAMILY."'; src: ".implode(', ', $sources).'; font-display: block; }';
    }

    /** The CSS font-family list of the card's texts. */
    public static function stack(): string
    {
        return "'".self::FAMILY."', ".self::FALLBACK;
    }

    /**
     * A text of the card, escaped, with its numbers in a font of their own.
     * The card's font draws 0-9 as Arabic-Indic digits (٢٠٢٦); the system
     * shows 0-9 everywhere, so numbers are drawn in the panel's font.
     */
    public static function withLatinDigits(string $text): HtmlString
    {
        return new HtmlString((string) preg_replace(
            '/[0-9]+(?:[.,\/:-][0-9]+)*/',
            '<tspan class="rawda-badge__digits">$0</tspan>',
            e($text),
        ));
    }

    /** The font file the mosque added, if any. */
    public static function file(): ?string
    {
        foreach (array_keys(self::FILES) as $extension) {
            if (is_file(public_path("fonts/card/card.{$extension}"))) {
                return "card.{$extension}";
            }
        }

        return null;
    }
}
