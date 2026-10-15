<?php

namespace App\Services\Market;

use App\Services\Files\KeptPictures;

/**
 * Where the pictures of the points markets' items are kept, for the sales
 * desk and the market's page (MarketItemPictureController hands them out).
 */
final class MarketItemPictures extends KeptPictures
{
    public const FOLDER = 'market-items';
}
