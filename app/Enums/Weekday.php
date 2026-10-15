<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;
use Carbon\CarbonInterface;

/**
 * Day of the week, numbered like Carbon's dayOfWeek (0 = Sunday).
 */
enum Weekday: int
{
    use HasOptions;

    case Sunday = 0;
    case Monday = 1;
    case Tuesday = 2;
    case Wednesday = 3;
    case Thursday = 4;
    case Friday = 5;
    case Saturday = 6;

    public static function fromDate(CarbonInterface $date): self
    {
        return self::from($date->dayOfWeek);
    }

    public function label(): string
    {
        return match ($this) {
            self::Sunday => 'الأحد',
            self::Monday => 'الاثنين',
            self::Tuesday => 'الثلاثاء',
            self::Wednesday => 'الأربعاء',
            self::Thursday => 'الخميس',
            self::Friday => 'الجمعة',
            self::Saturday => 'السبت',
        };
    }
}
