<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum PointSource: string
{
    use HasOptions;

    case Attendance = 'attendance';
    case Recitation = 'recitation';
    case Manual = 'manual';
    case Activity = 'activity';

    public function label(): string
    {
        return match ($this) {
            self::Attendance => 'حضور',
            self::Recitation => 'حفظ',
            self::Manual => 'يدوي',
            self::Activity => 'نشاط',
        };
    }
}
