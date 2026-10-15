<?php

namespace App\Services\Cards;

/**
 * The measures of the mosque's attendance card, taken from the designer's
 * Photoshop file (638 × 1016 px at 300 ppi): 54 × 86 mm, upright.
 *
 * The fixed part of the design is public/img/card/background.png; what
 * changes from card to card is drawn over it at the places of the PSD by
 * resources/views/cards/badge.blade.php, in the PSD's pixels.
 */
final class CardDesign
{
    public const WIDTH_MM = 54;

    public const HEIGHT_MM = 86;

    /** The colour of the name and of the QR code: the green of the design. */
    public const INK = '#525f2f';

    /** On an A4 sheet: 3 × 3 cards, 4 mm apart, centred. */
    public const SHEET_COLUMNS = 3;

    public const SHEET_ROWS = 3;

    public const SHEET_GAP_MM = 4;

    public static function cardsPerSheet(): int
    {
        return self::SHEET_COLUMNS * self::SHEET_ROWS;
    }
}
