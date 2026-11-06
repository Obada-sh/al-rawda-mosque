<?php

namespace App\Providers\Filament;

use App\Filament\Enums\PanelGroup;
use App\Filament\Support\Theme;
use App\Http\Controllers\AttendanceCardController;
use App\Http\Controllers\BackupDownloadController;
use App\Http\Controllers\CircleLogoController;
use App\Http\Controllers\CircleSheetController;
use App\Http\Controllers\MarketItemPictureController;
use App\Http\Controllers\MosqueSheetController;
use App\Http\Controllers\StudentFileController;
use App\Http\Controllers\StudentFilesController;
use App\Http\Controllers\StudentPhotoController;
use App\Http\Controllers\TeacherCardController;
use App\Http\Controllers\TeacherPhotoController;
use App\Http\Controllers\TickController;
use App\Providers\Filament\Concerns\SharesPanelSetup;
use Filament\Panel;
use Filament\PanelProvider;
use Illuminate\Support\Facades\Route;

/**
 * The panel of the mosque's courses («دورات المسجد», /admin): the first
 * section, where the system opens. Its settings, backups and accounts are
 * the whole system's.
 */
class AdminPanelProvider extends PanelProvider
{
    use SharesPanelSetup;

    public function panel(Panel $panel): Panel
    {
        return $this->sharedSetup($panel, Theme::PRIMARY)
            ->default()
            ->id('admin')
            ->path('admin')
            ->navigationGroups(PanelGroup::class)
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->authenticatedRoutes(function (): void {
                Route::get('attendance-cards', AttendanceCardController::class)->name('attendance-cards.print');
                Route::get('backups/{name}/download', BackupDownloadController::class)->name('backups.download');
                Route::get('teacher-cards', TeacherCardController::class)->name('teacher-cards.print');
                Route::get('student-photos/{student}', StudentPhotoController::class)->name('students.photo');
                Route::get('teacher-photos/{teacher}', TeacherPhotoController::class)->name('teachers.photo');
                Route::get('circle-logos/{circle}', CircleLogoController::class)->name('circles.logo');
                Route::get('market-items/{item}/picture', MarketItemPictureController::class)->name('market-items.picture');
                // The documents printed on A4 paper under the mosque's logo.
                Route::get('print/students/{student}', StudentFileController::class)->name('print.student-file');
                Route::get('print/students', StudentFilesController::class)->name('print.student-files');
                Route::get('print/circle-students', [CircleSheetController::class, 'students'])->name('print.circle-students');
                Route::get('print/circle-codes', [CircleSheetController::class, 'codes'])->name('print.circle-codes');
                Route::get('print/mosque-students', MosqueSheetController::class)->name('print.mosque-students');
                // Called by an open page every few minutes: what is due at a time of day happens then.
                Route::get('tick', TickController::class)->name('tick');
            });
    }
}
