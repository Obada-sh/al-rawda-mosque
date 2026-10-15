<?php

namespace App\Filament\Support;

use App\Services\Export\ExportedFile;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Sends a file made by the application layer to the admin's browser.
 * Returned from the action of a button, it starts the download.
 */
final class Download
{
    public static function of(ExportedFile $file): StreamedResponse
    {
        return response()->streamDownload(
            static function () use ($file): void {
                echo $file->contents;
            },
            $file->name,
            ['Content-Type' => $file->mimeType],
        );
    }
}
