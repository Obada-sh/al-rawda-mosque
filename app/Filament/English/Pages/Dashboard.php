<?php

namespace App\Filament\English\Pages;

use App\Filament\English\Widgets\EnglishOverviewStats;
use App\Filament\Widgets\MosqueBanner;
use Filament\Pages\Dashboard as BaseDashboard;

/**
 * The home page of the English section.
 */
class Dashboard extends BaseDashboard
{
    protected static ?string $title = 'دورة انجليزي';

    protected static ?string $navigationLabel = 'الرئيسية';

    public function getWidgets(): array
    {
        return [
            MosqueBanner::class,
            EnglishOverviewStats::class,
        ];
    }
}
