<?php

namespace App\Http\Controllers;

use App\Models\Circle;
use App\Services\Circles\CircleLogos;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Hands the logo of a circle to the signed-in admin (the panel and the
 * printed cards). Logos are not in the public folder.
 */
final class CircleLogoController extends Controller
{
    public function __invoke(Circle $circle, CircleLogos $logos): BinaryFileResponse
    {
        $path = $logos->path($circle->logo_path) ?? abort(404);

        // A new logo has a new address (see CirclePresenter::logoUrl), so this one never changes.
        return response()->file($path, ['Cache-Control' => 'private, max-age=31536000, immutable']);
    }
}
