<?php

namespace App\Filament\Widgets;

use App\Support\Brand;
use App\Support\Dates;
use Filament\Widgets\Widget;

/**
 * The top of the home page: the mosque's logo, its name and today's date.
 */
class MosqueBanner extends Widget
{
    protected static bool $isLazy = false;

    /** Listed by the pages that show it, not found on its own. */
    protected static bool $isDiscovered = false;

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'filament.widgets.mosque-banner';

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        return [
            'name' => app(Brand::class)->name(),
            'today' => Dates::showWithWeekday(Dates::today()),
        ];
    }
}
