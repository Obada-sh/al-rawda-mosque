<?php

namespace App\Actions\Market;

use App\Exceptions\BusinessRuleViolation;
use App\Models\Market;
use App\Models\MarketItem;
use App\Services\Market\MarketItemPictures;
use Illuminate\Support\Facades\DB;

/**
 * Deletes a market that never sold anything, with its items and their
 * pictures. A market with sales is closed instead: its sales are the
 * students' purchases.
 */
final class DeleteMarket
{
    public function __construct(private readonly MarketItemPictures $pictures) {}

    /**
     * @throws BusinessRuleViolation market.has_sales
     */
    public function handle(Market $market): void
    {
        if ($market->sales()->exists()) {
            throw BusinessRuleViolation::because('market.has_sales', 'لا يمكن حذف سوق فيه مبيعات. يمكنك إغلاقه بدلاً من ذلك.');
        }

        $pictures = $market->items()->pluck('image_path')->filter()->all();

        DB::transaction(function () use ($market): void {
            MarketItem::query()->where('market_id', $market->getKey())->delete();
            $market->delete();
        });

        foreach ($pictures as $picture) {
            $this->pictures->remove($picture);
        }
    }
}
