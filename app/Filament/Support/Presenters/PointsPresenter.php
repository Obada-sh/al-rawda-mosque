<?php

namespace App\Filament\Support\Presenters;

use App\Enums\PointSource;
use App\Models\PointTransaction;

/**
 * How points are coloured and told in the panel.
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

    /**
     * What a source adds up to, as the points desk tells it: "من الحفظ".
     */
    public static function sourceTotal(PointSource $source): string
    {
        return match ($source) {
            PointSource::Recitation => 'من الحفظ',
            PointSource::Attendance => 'من الحضور',
            PointSource::Activity => 'من الأنشطة',
            PointSource::Manual => 'مكافآت وخصومات',
        };
    }

    /**
     * A line of the ledger in a few words: its source, and its reason when
     * it has one ("نشاط — رحلة الربيع").
     */
    public static function line(PointTransaction $line): string
    {
        return filled($line->reason) ? "{$line->source->label()} — {$line->reason}" : $line->source->label();
    }

    /**
     * Points with their sign: "+5", "-3", "0".
     */
    public static function signed(int $points): string
    {
        return $points > 0 ? "+{$points}" : (string) $points;
    }
}
