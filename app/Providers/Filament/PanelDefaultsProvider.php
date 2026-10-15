<?php

namespace App\Providers\Filament;

use App\Support\Dates;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Support\ServiceProvider;

/**
 * Presentation defaults shared by every screen of the panel, so that no
 * screen has to repeat them (and none can drift from the others).
 */
class PanelDefaultsProvider extends ServiceProvider
{
    /**
     * Numbers are written with the digits 0-9 everywhere: in tables, in
     * inputs and on the printed cards.
     */
    private const NUMBER_LOCALE = 'en';

    /** Saturday, the first day of the week in Syria. */
    private const FIRST_DAY_OF_WEEK = 6;

    /**
     * Run by the picker once it is ready: its list of months becomes 1 to
     * 12 (the picker fills it with the names of the months first).
     */
    public const NUMBERED_MONTHS = 'months = Array.from({ length: 12 }, (_, month) => String(month + 1))';

    public function boot(): void
    {
        Table::configureUsing(function (Table $table): void {
            $table
                ->defaultDateDisplayFormat(Dates::DISPLAY_FORMAT)
                ->defaultDateTimeDisplayFormat(Dates::DISPLAY_FORMAT_WITH_TIME)
                ->defaultNumberLocale(self::NUMBER_LOCALE)
                ->paginationPageOptions([10, 25, 50, 100])
                ->defaultPaginationPageOption(25);
        });

        Schema::configureUsing(function (Schema $schema): void {
            $schema
                ->defaultDateDisplayFormat(Dates::DISPLAY_FORMAT)
                ->defaultDateTimeDisplayFormat(Dates::DISPLAY_FORMAT_WITH_TIME)
                ->defaultNumberLocale(self::NUMBER_LOCALE);
        });

        // The browser's own date box shows month/day or day/month depending
        // on the language of the browser. This picker always shows
        // day/month/year, whatever the laptop is set to, and its list of
        // months is numbered 1 to 12 instead of naming them.
        DateTimePicker::configureUsing(function (DateTimePicker $picker): void {
            $picker
                ->native(false)
                ->extraAlpineAttributes(['x-init' => self::NUMBERED_MONTHS])
                ->defaultDateDisplayFormat(Dates::DISPLAY_FORMAT)
                ->defaultDateTimeDisplayFormat(Dates::DISPLAY_FORMAT_WITH_TIME)
                ->defaultTimeDisplayFormat('H:i')
                ->seconds(false)
                ->firstDayOfWeek(self::FIRST_DAY_OF_WEEK)
                ->closeOnDateSelection();
        });

        Select::configureUsing(function (Select $select): void {
            $select->native(false);
        });
    }
}
