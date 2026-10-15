<?php

namespace App\Filament\Support\Presenters;

use App\Enums\PointSource;

/**
 * How points are coloured in the panel.
 */
final class PointsPresenter
{
    public static function sourceColor(PointSource $source): string
    {
        return match ($source) {
            PointSource::Attendance => 'info',
            PointSource::Recitation => 'success',
            PointSource::Manual => 'gray',
            PointSource::Activity => 'warning',
        };
    }

    /**
     * A penalty is red, a reward is green.
     */
    public static function amountColor(int $points): string
    {
        return $points < 0 ? 'danger' : 'success';
    }
}
