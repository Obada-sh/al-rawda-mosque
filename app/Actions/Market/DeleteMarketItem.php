<?php

namespace App\Actions\Market;

use App\Exceptions\BusinessRuleViolation;
use App\Models\MarketItem;
use App\Services\Market\MarketItemPictures;

/**
 * Deletes an item that was never sold, with its picture. An item that was
 * sold stays in the sales it is part of: it is taken off the desk instead.
 */
final class DeleteMarketItem
{
    public function __construct(private readonly MarketItemPictures $pictures) {}

    /**
     * @throws BusinessRuleViolation market_item.sold
     */
    public function handle(MarketItem $item): void
    {
        if ($item->saleLines()->exists()) {
            throw BusinessRuleViolation::because('market_item.sold', 'لا يمكن حذف بضاعة بيع منها شيء. أوقف عرضها في نافذة البيع بدلاً من ذلك.');
        }

        $item->delete();

        $this->pictures->remove($item->image_path);
    }
}
