<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/**
 * What students are ranked by.
 */
enum RankingCriterion: string
{
    use HasOptions;

    case Points = 'points';
    case NewPages = 'pages';
    case Attendance = 'attendance';

    public function label(): string
    {
        return match ($this) {
            self::Points => 'النقاط',
            self::NewPages => 'الصفحات الجديدة',
            self::Attendance => 'نسبة الحضور',
        };
    }
}
