<?php

namespace App\Services\Circles;

use App\Services\Files\KeptPictures;

/**
 * Where the logos of the circles are kept, for the attendance cards of
 * their students and teachers (CircleLogoController hands them out).
 */
final class CircleLogos extends KeptPictures
{
    public const FOLDER = 'circle-logos';
}
