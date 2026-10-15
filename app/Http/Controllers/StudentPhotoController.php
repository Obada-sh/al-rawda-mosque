<?php

namespace App\Http\Controllers;

use App\Models\Student;
use App\Services\Students\StudentPhotos;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Hands the photo of a student to the signed-in admin. Photos are not in
 * the public folder: this is the only way to one.
 */
final class StudentPhotoController extends Controller
{
    public function __invoke(Student $student, StudentPhotos $photos): BinaryFileResponse
    {
        $path = $photos->path($student->photo_path) ?? abort(404);

        // A new photo has a new address (see StudentPresenter::photoUrl), so this one never changes.
        return response()->file($path, ['Cache-Control' => 'private, max-age=31536000, immutable']);
    }
}
