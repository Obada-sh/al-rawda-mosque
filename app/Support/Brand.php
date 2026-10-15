<?php

namespace App\Support;

use App\Contracts\SettingsStore;

/**
 * The mosque's identity as every screen, printout and export shows it:
 * its logo and its name.
 *
 * The logo is one file of the application (public/img/logo.png, with a
 * see-through background), so it is the same everywhere: the panel, the
 * sign-in page, the reports, the cards and the slides.
 */
final class Brand
{
    /** Shown until the admin writes the mosque's name in the settings. */
    public const DEFAULT_NAME = 'نظام إدارة الجامع';

    public const LOGO = 'img/logo.png';

    public const FAVICON = 'img/favicon.png';

    public function __construct(
        private readonly SettingsStore $settings,
    ) {}

    public function name(): string
    {
        $name = trim((string) $this->settings->get(SettingKey::MOSQUE_NAME));

        return $name === '' ? self::DEFAULT_NAME : $name;
    }

    public static function logoUrl(): string
    {
        return asset(self::LOGO);
    }

    /** The logo on this machine's disk, for the files the system writes (the slides). */
    public static function logoFile(): string
    {
        return public_path(self::LOGO);
    }

    public static function faviconUrl(): string
    {
        return asset(self::FAVICON);
    }
}
