<?php

namespace App\Http\Controllers\English;

use App\Http\Controllers\Controller;
use App\Models\English\EnglishPaper;
use App\Services\English\EnglishPapers;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Hands a paper of an English course to the signed-in admin, under the
 * name it was uploaded with: a PDF or a picture opens in the browser,
 * anything else is saved. Papers are not in the public folder.
 */
final class EnglishPaperController extends Controller
{
    public function __invoke(EnglishPaper $paper, EnglishPapers $papers): BinaryFileResponse
    {
        $path = $papers->path($paper->file_path) ?? abort(404);

        return response()->download(
            $path,
            $paper->original_name,
            ['Content-Type' => $paper->mime_type, 'Cache-Control' => 'private, no-cache'],
            EnglishPapers::opensInBrowser($paper->file_path) ? 'inline' : 'attachment',
        );
    }
}
