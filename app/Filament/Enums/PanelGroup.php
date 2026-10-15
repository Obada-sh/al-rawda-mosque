<?php

namespace App\Filament\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * The sections of the side menu, in the order they appear.
 */
enum PanelGroup implements HasLabel
{
    case Students;
    case Daily;
    case Market;
    case Reports;
    case Finance;
    case Settings;

    public function getLabel(): string
    {
        return match ($this) {
            self::Students => 'الطلاب والدورات',
            self::Daily => 'المتابعة اليومية',
            self::Market => 'سوق النقاط',
            self::Reports => 'التقارير',
            self::Finance => 'المالية',
            self::Settings => 'الإعدادات',
        };
    }
}
