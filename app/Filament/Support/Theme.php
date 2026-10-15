<?php

namespace App\Filament\Support;

/**
 * The few visual constants that more than one part of the panel needs.
 */
final class Theme
{
    /** The Arabic typeface of the panel, served from this machine. */
    public const FONT_FAMILY = 'IBM Plex Sans Arabic';

    /** The background of cards and charts. */
    public const SURFACE = '#ffffff';

    /**
     * The green of the panel, from the lightest shade to the darkest.
     *
     * It is the stock emerald moved one step darker, with a shade of its
     * own at 500. The stock one is too light to carry white text, so the
     * panel would draw its buttons in mint with dark text; with these
     * shades the buttons are deep green with white text, and green text
     * keeps the colour it had.
     *
     * @var array<int, string>
     */
    public const PRIMARY = [
        50 => 'oklch(0.979 0.021 166.113)',
        100 => 'oklch(0.95 0.052 163.051)',
        200 => 'oklch(0.905 0.093 164.15)',
        300 => 'oklch(0.845 0.143 164.978)',
        400 => 'oklch(0.696 0.17 162.48)',
        500 => 'oklch(0.552 0.132 164.4)',
        600 => 'oklch(0.508 0.118 165.612)',
        700 => 'oklch(0.432 0.095 166.913)',
        800 => 'oklch(0.378 0.077 168.94)',
        900 => 'oklch(0.262 0.051 172.552)',
        950 => 'oklch(0.2 0.04 172)',
    ];

    /**
     * Colours that tell series apart in a chart, in a fixed order.
     *
     * The order is part of the design: neighbours stay distinguishable for
     * colour-blind readers (checked with a palette validator), so a series
     * always takes the next colour and colours are never generated.
     *
     * @var list<string>
     */
    public const SERIES_COLORS = [
        '#2a78d6', // blue
        '#eb6834', // orange
        '#1baf7a', // aqua
        '#eda100', // yellow
        '#e87ba4', // magenta
        '#008300', // green
        '#4a3aa7', // violet
        '#e34948', // red
    ];
}
