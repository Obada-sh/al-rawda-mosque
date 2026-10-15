<?php

namespace App\Services\Students;

use App\Services\Files\KeptPictures;

/**
 * Where the photos of the students are kept. A photo is personal: only the
 * signed-in admin is handed it (StudentPhotoController).
 */
final class StudentPhotos extends KeptPictures
{
    public const FOLDER = 'student-photos';
}
