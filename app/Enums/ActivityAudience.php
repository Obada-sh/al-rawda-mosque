<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/**
 * Who an activity is for. It is not stored: an activity that names a
 * circle is for that circle, one that names none is for the whole mosque.
 */
enum ActivityAudience: string
{
    use HasOptions;

    case Circle = 'circle';
    case Mosque = 'mosque';

    public function label(): string
    {
        return match ($this) {
            self::Circle => 'حلقة',
            self::Mosque => 'الجامع كله',
        };
    }
}
