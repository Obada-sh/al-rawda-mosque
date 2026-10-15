<?php

namespace Tests\Feature\Panel;

use App\Filament\Pages\Reports\Statistics;
use App\Providers\Filament\PanelDefaultsProvider;

/**
 * No month is named anywhere: dates are written in numbers, and the list
 * of months of the date picker is numbered 1 to 12.
 */
class NumberedMonthsTest extends PanelTestCase
{
    public function test_the_date_picker_numbers_its_months(): void
    {
        $this->assertSame(
            'months = Array.from({ length: 12 }, (_, month) => String(month + 1))',
            PanelDefaultsProvider::NUMBERED_MONTHS,
        );

        // The period fields of a report are date pickers.
        $this->get(Statistics::getUrl())
            ->assertOk()
            ->assertSee('x-init="'.PanelDefaultsProvider::NUMBERED_MONTHS.'"', escape: false);
    }
}
