<?php

namespace App\Providers\Filament\Concerns;

use App\Filament\Pages\Auth\EditProfile;
use App\Filament\Pages\Auth\Login;
use App\Filament\Support\DemoNotice;
use App\Filament\Support\SectionSwitcher;
use App\Filament\Support\Theme;
use App\Http\Middleware\RedirectToSetupWhenNoAdmin;
use App\Http\Middleware\RemindAdmin;
use App\Http\Middleware\TakeDailyBackup;
use App\Http\Middleware\TakeEveningDriveCopy;
use App\Services\Backup\DriveCopySetup;
use App\Support\Brand;
use Filament\Facades\Filament;
use Filament\FontProviders\LocalFontProvider;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\Width;
use Filament\View\PanelsRenderHook;
use Illuminate\Contracts\View\View;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\HtmlString;
use Illuminate\View\Middleware\ShareErrorsFromSession;

/**
 * What the panels of the two sections (the mosque's courses, the English
 * courses) have in common: Arabic, right-to-left and fully offline (the
 * font and every script are served from this machine), the same sign-in
 * and account, the switch between the sections at the top of every page,
 * and the same daily backup and reminders, whichever section the admin
 * works in.
 *
 * Each panel adds its id, its address, its colour and its own screens.
 */
trait SharesPanelSetup
{
    /**
     * @param  array<int|string, string|int>  $primary  the section's colour
     */
    protected function sharedSetup(Panel $panel, array $primary): Panel
    {
        return $panel
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
                'primary' => $primary,
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
            // The switch between the two sections.
            ->renderHook(PanelsRenderHook::TOPBAR_LOGO_AFTER, fn (SectionSwitcher $switcher): View => $switcher->render())
            ->renderHook(PanelsRenderHook::AUTH_LOGIN_FORM_BEFORE, fn (DemoNotice $notice): ?View => $notice->signIn())
            // An open page of the signed-in admin keeps the evening copy for Google Drive on time.
            ->renderHook(PanelsRenderHook::BODY_END, fn (DriveCopySetup $driveCopy): ?View => Filament::auth()->check() && config('rawda.backups.drive') && $driveCopy->settings()->isOn()
                ? view('filament.partials.tick')
                : null)
            ->maxContentWidth(Width::Full)
            ->sidebarCollapsibleOnDesktop()
            ->databaseNotifications()
            ->databaseNotificationsPolling('30s')
            ->unsavedChangesAlerts()
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
                TakeEveningDriveCopy::class,
            ]);
    }
}
