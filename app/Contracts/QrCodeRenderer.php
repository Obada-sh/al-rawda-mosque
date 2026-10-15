<?php

namespace App\Contracts;

/**
 * Draws a QR code. The application layer depends on this contract only,
 * so the library that draws the code can be replaced.
 */
interface QrCodeRenderer
{
    /**
     * @param  string  $color  the colour of the modules, "#rrggbb"; between them the code is see-through
     * @param  bool  $quietZone  whether the code keeps the blank border scanners like; without it,
     *                           whatever is printed around the code must be light
     * @return string an inline <svg> element that scales to its container
     */
    public function svg(string $content, string $color = '#000000', bool $quietZone = true): string;
}
