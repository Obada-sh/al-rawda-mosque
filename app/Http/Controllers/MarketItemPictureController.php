<?php

namespace App\Http\Controllers;

use App\Models\MarketItem;
use App\Services\Market\MarketItemPictures;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Hands the picture of a market's item to the signed-in admin (the sales
 * desk and the market's page). Pictures are not in the public folder.
 */
final class MarketItemPictureController extends Controller
{
    public function __invoke(MarketItem $item, MarketItemPictures $pictures): BinaryFileResponse
    {
        $path = $pictures->path($item->image_path) ?? abort(404);

        // A new picture has a new address (see MarketPresenter::pictureUrl), so this one never changes.
        return response()->file($path, ['Cache-Control' => 'private, max-age=31536000, immutable']);
    }
}
