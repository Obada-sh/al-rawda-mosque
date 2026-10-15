<?php

namespace App\Http\Controllers;

use App\Models\Teacher;
use App\Services\Teachers\TeacherPhotos;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Hands the photo of a teacher to the signed-in admin. Photos are not in
 * the public folder: this is the only way to one.
 */
final class TeacherPhotoController extends Controller
{
    public function __invoke(Teacher $teacher, TeacherPhotos $photos): BinaryFileResponse
    {
        $path = $photos->path($teacher->photo_path) ?? abort(404);

        // A new photo has a new address (see TeacherPresenter::photoUrl), so this one never changes.
        return response()->file($path, ['Cache-Control' => 'private, max-age=31536000, immutable']);
    }
}
