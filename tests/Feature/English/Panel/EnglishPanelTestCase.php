<?php

namespace Tests\Feature\English\Panel;

use Filament\Facades\Filament;
use Tests\Concerns\BuildsEnglish;
use Tests\Feature\Panel\PanelTestCase;

/**
 * Base of the tests that drive the English section's panel as the
 * signed-in admin, on a fixed Wednesday afternoon.
 */
abstract class EnglishPanelTestCase extends PanelTestCase
{
    use BuildsEnglish;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('english');
    }
}
