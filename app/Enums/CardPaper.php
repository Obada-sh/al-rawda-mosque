<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/**
 * What the attendance cards are printed on.
 */
enum CardPaper: string
{
    use HasOptions;

    /** As many cards as fit on an A4 sheet, to cut out. */
    case Sheet = 'sheet';

    /** One card on a page of its own size: a card printer. */
    case Single = 'single';

    public function label(): string
    {
        return match ($this) {
            self::Sheet => 'ورقة A4 فيها عدة بطاقات',
            self::Single => 'بطاقة في كل صفحة',
        };
    }
}
