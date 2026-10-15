<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\MosqueBanner;
use App\Filament\Widgets\OverviewStats;
use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    protected static ?string $title = 'الرئيسية';

    public function getWidgets(): array
    {
        return [
            MosqueBanner::class,
            OverviewStats::class,
        ];
    }
}
