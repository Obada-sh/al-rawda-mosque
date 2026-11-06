<?php

namespace App\Providers\Filament;

use App\Filament\English\EnglishPanelGroup;
use App\Filament\Support\Theme;
use App\Http\Controllers\English\EnglishCardBackgroundController;
use App\Http\Controllers\English\EnglishCardController;
use App\Http\Controllers\English\EnglishPaperController;
use App\Providers\Filament\Concerns\SharesPanelSetup;
use Filament\Panel;
use Filament\PanelProvider;
use Illuminate\Support\Facades\Route;

/**
 * The panel of the English courses («دورة انجليزي», /english): a section
 * of its own, with its own courses, halls, teachers, attendance, money,
 * tests and papers. It shares only the students with the mosque's panel.
 *
 * Its screens live in app/Filament/English, apart from the mosque's, so
 * neither panel ever finds the other's screens.
 */
class EnglishPanelProvider extends PanelProvider
{
    use SharesPanelSetup;

    public function panel(Panel $panel): Panel
    {
        return $this->sharedSetup($panel, Theme::ENGLISH_PRIMARY)
            ->id('english')
            ->path('english')
            ->navigationGroups(EnglishPanelGroup::class)
            ->discoverResources(in: app_path('Filament/English/Resources'), for: 'App\Filament\English\Resources')
            ->discoverPages(in: app_path('Filament/English/Pages'), for: 'App\Filament\English\Pages')
            ->discoverWidgets(in: app_path('Filament/English/Widgets'), for: 'App\Filament\English\Widgets')
            ->authenticatedRoutes(function (): void {
                Route::get('card-designs/{level}', EnglishCardBackgroundController::class)->name('card-designs');
                Route::get('cards', EnglishCardController::class)->name('cards');
                Route::get('papers/{paper}/file', EnglishPaperController::class)->name('papers.file');
            });
    }
}
