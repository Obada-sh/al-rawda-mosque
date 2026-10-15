<?php

namespace App\Services\Cards;

use App\Contracts\QrCodeRenderer;
use BaconQrCode\Common\ErrorCorrectionLevel;
use BaconQrCode\Encoder\Encoder;
use BaconQrCode\Renderer\Color\Alpha;
use BaconQrCode\Renderer\Color\Rgb;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\Fill;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use InvalidArgumentException;

/**
 * QR codes drawn as SVG: sharp at any print size, and no image extension
 * (GD / Imagick) is needed on the mosque laptop.
 */
final class BaconQrCodeRenderer implements QrCodeRenderer
{
    private const SIZE = 300;

    /** Quiet zone around the code, in modules. Scanners need at least 4. */
    private const MARGIN = 4;

    public function svg(string $content, string $color = '#000000', bool $quietZone = true): string
    {
        if (preg_match('/^#([0-9a-fA-F]{2})([0-9a-fA-F]{2})([0-9a-fA-F]{2})$/', $color, $rgb) !== 1) {
            throw new InvalidArgumentException("Not a colour: {$color}");
        }

        $fill = Fill::uniformColor(
            new Alpha(0, new Rgb(255, 255, 255)),
            new Rgb((int) hexdec($rgb[1]), (int) hexdec($rgb[2]), (int) hexdec($rgb[3])),
        );

        $writer = new Writer(new ImageRenderer(
            new RendererStyle(self::SIZE, $quietZone ? self::MARGIN : 0, null, null, $fill),
            new SvgImageBackEnd,
        ));

        // Level Q survives a card that is scratched or partly smudged.
        $svg = $writer->writeString($content, Encoder::DEFAULT_BYTE_MODE_ENCODING, ErrorCorrectionLevel::Q());

        // The XML declaration is not allowed inside an HTML page.
        return trim((string) preg_replace('/^<\?xml[^>]*\?>\s*/', '', $svg));
    }
}
