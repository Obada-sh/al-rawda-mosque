<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum ExamGrade: string
{
    use HasOptions;

    case Excellent = 'excellent';
    case VeryGood = 'very_good';
    case Good = 'good';
    case Acceptable = 'acceptable';
    case Retake = 'retake';

    public function label(): string
    {
        return match ($this) {
            self::Excellent => 'ممتاز',
            self::VeryGood => 'جيد جداً',
            self::Good => 'جيد',
            self::Acceptable => 'مقبول',
            self::Retake => 'إعادة',
        };
    }
}
