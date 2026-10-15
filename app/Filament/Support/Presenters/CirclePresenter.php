<?php

namespace App\Filament\Support\Presenters;

use App\Models\Circle;

/**
 * What the panel shows of a circle besides its name.
 */
final class CirclePresenter
{
    /**
     * The address of the circle's logo for the signed-in admin, or null
     * when the circle has none. It changes with the logo.
     */
    public static function logoUrl(?Circle $circle): ?string
    {
        return self::logoUrlOf($circle?->getKey(), $circle?->logo_path);
    }

    public static function logoUrlOf(?int $circleId, ?string $logo): ?string
    {
        if ($circleId === null || blank($logo)) {
            return null;
        }

        return route('filament.admin.circles.logo', [
            'circle' => $circleId,
            'v' => pathinfo($logo, PATHINFO_FILENAME),
        ]);
    }

    /**
     * Who teaches the circle: "المشرف: الشيخ ياسر · المساعد: الأستاذ بلال",
     * or null when it has neither.
     *
     * @param  Circle  $circle  with its supervisor and assistant
     */
    public static function team(Circle $circle): ?string
    {
        $team = array_filter([
            $circle->supervisor === null ? null : 'المشرف: '.$circle->supervisor->full_name,
            $circle->assistant === null ? null : 'المساعد: '.$circle->assistant->full_name,
        ]);

        return $team === [] ? null : implode(' · ', $team);
    }
}
