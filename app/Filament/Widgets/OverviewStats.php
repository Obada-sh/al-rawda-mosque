<?php

namespace App\Filament\Widgets;

use App\Services\Statistics\DashboardFigures;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * The headline numbers at the top of the home page.
 */
class OverviewStats extends StatsOverviewWidget
{
    /** Drawn with the page itself: the figures are cheap, and one request is faster than two. */
    protected static bool $isLazy = false;

    protected ?string $pollingInterval = '60s';

    protected function getStats(): array
    {
        $figures = app(DashboardFigures::class);

        $incompleteProfiles = $figures->incompleteProfiles();
        $pendingExams = $figures->pendingExams();

        return [
            Stat::make('الطلاب', $figures->activeStudents())
                ->description($incompleteProfiles > 0 ? "ملفات غير مكتملة: {$incompleteProfiles}" : 'كل الملفات مكتملة')
                ->descriptionIcon($incompleteProfiles > 0 ? Heroicon::OutlinedExclamationTriangle : Heroicon::OutlinedCheckCircle)
                ->color($incompleteProfiles > 0 ? 'warning' : 'success'),

            Stat::make('الدورات النشطة', $figures->activeCourses())
                ->description("المسجَّلون فيها: {$figures->enrolledStudents()}")
                ->descriptionIcon(Heroicon::OutlinedAcademicCap)
                ->color('primary'),

            Stat::make('حضور اليوم', $figures->attendanceToday())
                ->description("الطلاب في كل الدورات · الأساتذة: {$figures->teachersPresentToday()}")
                ->descriptionIcon(Heroicon::OutlinedQrCode)
                ->color('primary'),

            Stat::make('صفحات جديدة هذا الشهر', $figures->newPagesThisMonth())
                ->description('محفوظ جديد غير مكرر')
                ->descriptionIcon(Heroicon::OutlinedBookOpen)
                ->color('primary'),

            Stat::make('سبر منتظر', $pendingExams)
                ->description($pendingExams > 0 ? 'طلاب أتموا أجزاء ولم يُسبروا' : 'لا يوجد سبر منتظر')
                ->descriptionIcon($pendingExams > 0 ? Heroicon::OutlinedClock : Heroicon::OutlinedCheckCircle)
                ->color($pendingExams > 0 ? 'warning' : 'success'),
        ];
    }
}
