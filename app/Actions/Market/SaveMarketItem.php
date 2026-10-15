<?php

namespace App\Actions\Market;

use App\Http\Requests\Market\MarketItemRequest;
use App\Models\Market;
use App\Models\MarketItem;
use App\Services\Market\MarketItemPictures;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

/**
 * Adds an item to a market, or changes one: name, description, price in
 * points, what is left to sell, whether the desk offers it, and its
 * picture (a new one replaces the old; "remove_image" takes it away).
 *
 * A new price applies to the next sales; what was sold keeps the price it
 * was sold at.
 */
final class SaveMarketItem
{
    public function __construct(private readonly MarketItemPictures $pictures) {}

    /**
     * @param  array<string, mixed>  $input
     *
     * @throws ValidationException
     */
    public function handle(Market $market, array $input, ?MarketItem $item = null): MarketItem
    {
        $data = MarketItemRequest::validateData($input, [
            'market_id' => $market->getKey(),
            'ignore_id' => $item?->getKey(),
        ]);

        /** @var UploadedFile|null $image */
        $image = $data['image'] ?? null;
        $removeImage = (bool) ($data['remove_image'] ?? false);

        $item ??= new MarketItem(['market_id' => $market->getKey()]);

        $item->fill([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'price' => (int) $data['price'],
            'stock' => isset($data['stock']) ? (int) $data['stock'] : null,
            'is_available' => (bool) ($data['is_available'] ?? $item->is_available),
        ])->save();

        if ($image !== null) {
            $this->changePicture($item, $this->pictures->keep($item, $image));
        } elseif ($removeImage) {
            $this->changePicture($item, null);
        }

        return $item;
    }

    private function changePicture(MarketItem $item, ?string $name): void
    {
        $previous = $item->image_path;

        $item->forceFill(['image_path' => $name])->save();

        if ($previous !== $name) {
            $this->pictures->remove($previous);
        }
    }
}
