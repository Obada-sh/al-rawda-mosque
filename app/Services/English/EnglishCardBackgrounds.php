<?php

namespace App\Services\English;

use App\Services\Files\KeptPictures;

/**
 * Where the card designs of the English levels are kept: the whole face of
 * the attendance card of a level's students (EnglishCardBackgroundController
 * hands them out to the panel and to the printed cards).
 */
final class EnglishCardBackgrounds extends KeptPictures
{
    public const FOLDER = 'english-cards';
}
