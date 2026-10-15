<?php

namespace App\Http\Controllers;

use App\Http\Requests\Backups\BackupNameRequest;
use App\Services\Backup\BackupStore;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Hands a backup file to the admin's browser, to be saved outside this
 * computer (opened from the backups page of the panel).
 */
final class BackupDownloadController extends Controller
{
    public function __invoke(string $name, BackupStore $store): BinaryFileResponse
    {
        try {
            $name = (string) BackupNameRequest::validateData(['name' => $name])['name'];
        } catch (ValidationException) {
            abort(404);
        }

        $backup = $store->find($name) ?? abort(404);

        return response()->download($backup->path, $backup->name, ['Content-Type' => 'application/zip']);
    }
}
