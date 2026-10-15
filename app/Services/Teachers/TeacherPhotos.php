<?php

namespace App\Services\Teachers;

use App\Services\Files\KeptPictures;

/**
 * Where the photos of the teachers are kept, for their attendance cards.
 * Only the signed-in admin is handed them (TeacherPhotoController).
 */
final class TeacherPhotos extends KeptPictures
{
    public const FOLDER = 'teacher-photos';
}
