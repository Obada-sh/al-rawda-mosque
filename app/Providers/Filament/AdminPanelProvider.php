<?php

namespace App\Providers\Filament;

use App\Filament\Enums\PanelGroup;
use App\Filament\Pages\Auth\EditProfile;
use App\Filament\Pages\Auth\Login;
use App\Filament\Support\DemoNotice;
use App\Filament\Support\Theme;
use App\Http\Controllers\AttendanceCardController;
use App\Http\Controllers\BackupDownloadController;
use App\Http\Controllers\CircleLogoController;
use App\Http\Controllers\CircleSheetController;
use App\Http\Controllers\MarketItemPictureController;
use App\Http\Controllers\StudentFileController;
use App\Http\Controllers\StudentPhotoController;
use App\Http\Controllers\TeacherCardController;
use App\Http\Controllers\TeacherPhotoController;
use App\Http\Middleware\RedirectToSetupWhenNoAdmin;
use App\Http\Middleware\RemindAdmin;
use App\Http\Middleware\TakeDailyBackup;
use App\Support\Brand;
use Filament\FontProviders\LocalFontProvider;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\Width;
use Filament\View\PanelsRenderHook;
use Illuminate\Contracts\View\View;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\HtmlString;
use Illuminate\View\Middleware\ShareErrorsFromSession;

/**
 * The admin panel: Arabic, right-to-left, and fully offline (the font and
 * every script are served from this machine).
 */
class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login(Login::class)
            ->profile(EditProfile::class, isSimple: false)
            ->brandName(fn (Brand $brand): string => $brand->name())
            // The mosque's logo with its name, at the top of every page and above the sign-in form.
            ->brandLogo(fn (Brand $brand): View => view('filament.brand.logo', ['name' => $brand->name()]))
            ->brandLogoHeight('3rem')
            ->favicon(fn (): string => Brand::faviconUrl())
            ->font(
                Theme::FONT_FAMILY,
                url: fn (): string => asset('fonts/ibm-plex-sans-arabic/font.css'),
                provider: LocalFontProvider::class,
            )
            ->colors([
                'primary' => Theme::PRIMARY,
                // The third place of a ranking (gold and silver are "warning" and "gray").
                'bronze' => Color::Orange,
            ])
            ->darkMode(false)
            ->renderHook(
                PanelsRenderHook::HEAD_END,
                fn (): HtmlString => new HtmlString('<link rel="stylesheet" href="'.e(asset('css/panel.css')).'">'),
            )
            // The demo says that it is the demo; the real system shows nothing here.
            ->renderHook(PanelsRenderHook::TOPBAR_LOGO_AFTER, fn (DemoNotice $notice): ?View => $notice->badge())
            ->renderHook(PanelsRenderHook::AUTH_LOGIN_FORM_BEFORE, fn (DemoNotice $notice): ?View => $notice->signIn())
            ->maxContentWidth(Width::Full)
            ->sidebarCollapsibleOnDesktop()
            ->navigationGroups(PanelGroup::class)
            ->databaseNotifications()
            ->databaseNotificationsPolling('30s')
            ->unsavedChangesAlerts()
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
                Route::get('print/circle-students', [CircleSheetController::class, 'students'])->name('print.circle-students');
                Route::get('print/circle-codes', [CircleSheetController::class, 'codes'])->name('print.circle-codes');
            })
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
                RedirectToSetupWhenNoAdmin::class,
            ])
            ->authMiddleware([
                Authenticate::class,
                RemindAdmin::class,
                TakeDailyBackup::class,
            ]);
    }
}
