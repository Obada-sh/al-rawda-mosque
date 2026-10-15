<?php

namespace App\Filament\Support\Presenters;

use App\Actions\Cards\AttendanceCard;

/**
 * The addresses the printed card needs: the owner's photo, the circle's
 * logo, and the QR code as a picture.
 */
final class CardPresenter
{
    public static function photoUrl(AttendanceCard $card): ?string
    {
        return $card->teacherId !== null
            ? TeacherPresenter::photoUrlOf($card->teacherId, $card->photo)
            : StudentPresenter::photoUrlOf($card->studentId, $card->photo);
    }

    public static function logoUrl(AttendanceCard $card): ?string
    {
        return CirclePresenter::logoUrlOf($card->circleId, $card->circleLogo);
    }

    /** The QR drawing as an address an <image> can show, sharp at any size. */
    public static function qrDataUri(AttendanceCard $card): string
    {
        return self::svgDataUri($card->qrSvg);
    }

    /** An SVG drawing as an address an <img> or an <image> can show. */
    public static function svgDataUri(string $svg): string
    {
        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }
}
