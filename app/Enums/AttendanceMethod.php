<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum AttendanceMethod: string
{
    use HasOptions;

    case Qr = 'qr';
    case Manual = 'manual';

    public function label(): string
    {
        return match ($this) {
            self::Qr => 'مسح البطاقة',
            self::Manual => 'يدوي',
        };
    }
}
