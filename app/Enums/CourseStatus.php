<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum CourseStatus: string
{
    use HasOptions;

    case Active = 'active';
    case Finished = 'finished';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'نشطة',
            self::Finished => 'منتهية',
        };
    }
}
