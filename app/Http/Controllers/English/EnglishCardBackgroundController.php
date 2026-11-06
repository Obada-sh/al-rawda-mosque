<?php

namespace App\Http\Controllers\English;

use App\Http\Controllers\Controller;
use App\Models\English\EnglishLevel;
use App\Services\English\EnglishCardBackgrounds;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Hands the card design of an English level to the signed-in admin (the
 * panel and the printed cards). Designs are not in the public folder.
 */
final class EnglishCardBackgroundController extends Controller
{
    public function __invoke(EnglishLevel $level, EnglishCardBackgrounds $backgrounds): BinaryFileResponse
    {
        $path = $backgrounds->path($level->card_background_path) ?? abort(404);

        // A new design has a new address (see EnglishPresenter::cardBackgroundUrl), so this one never changes.
        return response()->file($path, ['Cache-Control' => 'private, max-age=31536000, immutable']);
    }
}
