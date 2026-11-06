<?php

namespace App\Filament\English\Widgets;

use App\Services\English\EnglishDashboardFigures;
use App\Support\Money;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * The headline numbers at the top of the English section's home page.
 */
class EnglishOverviewStats extends StatsOverviewWidget
{
    /** Drawn with the page itself: the figures are cheap, and one request is faster than two. */
    protected static bool $isLazy = false;

    /** Listed by the home page, not found on its own. */
    protected static bool $isDiscovered = false;

    protected ?string $pollingInterval = '60s';

    protected function getStats(): array
    {
        $figures = app(EnglishDashboardFigures::class);

        $incompleteProfiles = $figures->incompleteProfiles();

        return [
            Stat::make('الطلاب', $figures->students())
                ->description($incompleteProfiles > 0 ? "ملفات غير مكتملة: {$incompleteProfiles}" : 'كل الملفات مكتملة')
                ->descriptionIcon($incompleteProfiles > 0 ? Heroicon::OutlinedExclamationTriangle : Heroicon::OutlinedCheckCircle)
                ->color($incompleteProfiles > 0 ? 'warning' : 'success'),

            Stat::make('الدورات الجارية', $figures->activeCourses())
                ->description("المسجَّلون فيها: {$figures->enrolledStudents()}")
                ->descriptionIcon(Heroicon::OutlinedAcademicCap)
                ->color('primary'),

            Stat::make('حضور اليوم', $figures->attendanceToday())
                ->description('الطلاب في كل الدورات')
                ->descriptionIcon(Heroicon::OutlinedQrCode)
                ->color('primary'),

            Stat::make('المدفوع هذا الشهر', Money::pounds($figures->paidThisMonth()))
                ->description('دفعات الطلاب عن الدورات')
                ->descriptionIcon(Heroicon::OutlinedBanknotes)
                ->color('primary'),
        ];
    }
}
