<?php

namespace App\Actions\Market;

use App\Models\MarketItem;
use App\Models\MarketSale;
use Illuminate\Support\Facades\DB;

/**
 * Cancels a sale made by mistake: the points go back to the student's
 * balance and what was taken from the items' stock goes back to them.
 */
final class CancelSale
{
    public function handle(MarketSale $sale): void
    {
        DB::transaction(function () use ($sale): void {
            $lines = $sale->lines()->get();

            $items = MarketItem::query()
                ->whereKey($lines->pluck('market_item_id')->all())
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            foreach ($lines as $line) {
                $item = $items->get($line->market_item_id);

                if ($item?->stock !== null) {
                    $item->increment('stock', $line->quantity);
                }
            }

            // Its lines go with it (cascade).
            $sale->delete();
        });
    }
}
