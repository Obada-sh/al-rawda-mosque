<?php

namespace App\Filament\Support\Presenters;

use App\Models\MarketItem;
use App\Models\MarketSale;
use App\Models\MarketSaleLine;
use App\Support\ArabicNoun;

/**
 * How the points market is written in the panel.
 */
final class MarketPresenter
{
    /**
     * The address of the item's picture for the signed-in admin, or null
     * when it has none. It changes with the picture.
     */
    public static function pictureUrl(?MarketItem $item): ?string
    {
        if ($item === null || blank($item->image_path)) {
            return null;
        }

        return route('filament.admin.market-items.picture', [
            'item' => $item->getKey(),
            'v' => pathinfo($item->image_path, PATHINFO_FILENAME),
        ]);
    }

    /** "45 نقطة", "نقطتان", "5 نقاط". */
    public static function points(int $points): string
    {
        return ArabicNoun::point()->count(max(0, $points));
    }

    /**
     * What is left of an item: "بقي 4", "نفدت", or null when there is no limit.
     */
    public static function stock(MarketItem $item): ?string
    {
        return match (true) {
            $item->stock === null => null,
            $item->stock === 0 => 'نفدت',
            default => 'بقي '.$item->stock,
        };
    }

    /**
     * What a sale was made of: "قلم ×2، دفتر".
     *
     * @param  MarketSale  $sale  with its lines
     */
    public static function lines(MarketSale $sale): string
    {
        return $sale->lines
            ->map(fn (MarketSaleLine $line): string => $line->quantity > 1 ? "{$line->item_name} ×{$line->quantity}" : $line->item_name)
            ->implode('، ');
    }
}
