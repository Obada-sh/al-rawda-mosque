<?php

namespace App\Filament\Support;

use App\Enums\Section;
use Filament\Facades\Filament;
use Illuminate\Contracts\View\View;

/**
 * The switch between the two sections at the top of every page: the
 * mosque's courses and the English courses, each with its own menu and
 * home page. The section the admin is in is the highlighted one.
 */
final class SectionSwitcher
{
    public function render(): View
    {
        $current = Filament::getCurrentPanel()?->getId();

        return view('filament.partials.section-switcher', [
            'sections' => array_map(fn (Section $section): array => [
                'label' => $section->label(),
                'url' => Filament::getPanel($section->panelId())->getUrl(),
                'current' => $section->panelId() === $current,
            ], Section::cases()),
        ]);
    }
}
