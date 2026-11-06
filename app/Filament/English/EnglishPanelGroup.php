<?php

namespace App\Filament\English;

use Filament\Support\Contracts\HasLabel;

/**
 * The sections of the side menu of the English courses, in the order
 * they appear.
 */
enum EnglishPanelGroup implements HasLabel
{
    case Courses;
    case Daily;
    case Finance;
    case Settings;

    public function getLabel(): string
    {
        return match ($this) {
            self::Courses => 'الطلاب والدورات',
            self::Daily => 'المتابعة اليومية',
            self::Finance => 'المالية',
            self::Settings => 'الإعدادات',
        };
    }
}
