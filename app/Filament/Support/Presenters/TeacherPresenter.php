<?php

namespace App\Filament\Support\Presenters;

use App\Models\Teacher;

/**
 * What the panel shows of a teacher besides their name.
 */
final class TeacherPresenter
{
    /**
     * The address of the teacher's photo for the signed-in admin, or null
     * when the teacher has none. It changes with the photo.
     */
    public static function photoUrl(?Teacher $teacher): ?string
    {
        return self::photoUrlOf($teacher?->getKey(), $teacher?->photo_path);
    }

    public static function photoUrlOf(?int $teacherId, ?string $photo): ?string
    {
        if ($teacherId === null || blank($photo)) {
            return null;
        }

        return route('filament.admin.teachers.photo', [
            'teacher' => $teacherId,
            'v' => pathinfo($photo, PATHINFO_FILENAME),
        ]);
    }
}
