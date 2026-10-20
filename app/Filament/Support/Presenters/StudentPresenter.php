<?php

namespace App\Filament\Support\Presenters;

use App\Models\JuzExam;
use App\Models\Student;
use App\Services\Students\ProfileCompleteness;

/**
 * The marks shown next to a student in the panel.
 */
final class StudentPresenter
{
    /**
     * "ينقصه: تاريخ الميلاد، رقم ولي الأمر", or null when the profile is complete.
     */
    public static function missingFields(Student $student): ?string
    {
        $missing = app(ProfileCompleteness::class)->missingLabels($student->getAttributes());

        return $missing === [] ? null : 'ينقصه: '.implode('، ', $missing);
    }

    /**
     * "اسم الأب: محمد" under the name in the list of students, or null when
     * the father's name was not written.
     */
    public static function father(Student $student): ?string
    {
        return filled($student->father_name) ? 'اسم الأب: '.$student->father_name : null;
    }

    /**
     * The address of the student's photo for the signed-in admin, or null
     * when the student has none. The address changes with the photo, so a
     * browser can keep a picture as long as it likes.
     */
    public static function photoUrl(?Student $student): ?string
    {
        return self::photoUrlOf($student?->getKey(), $student?->photo_path);
    }

    /**
     * The same, for whoever holds the number of the student and the name
     * of the file instead of the student (a card being printed).
     */
    public static function photoUrlOf(?int $studentId, ?string $photo): ?string
    {
        if ($studentId === null || blank($photo)) {
            return null;
        }

        return route('filament.admin.students.photo', [
            'student' => $studentId,
            'v' => pathinfo($photo, PATHINFO_FILENAME),
        ]);
    }

    /**
     * The juz the student has completed and not been examined on yet.
     *
     * @return list<string> e.g. ["الجزء 29", "الجزء 30"]
     */
    public static function pendingExams(Student $student): array
    {
        return $student->juzExams
            ->filter(fn (JuzExam $exam): bool => $exam->isPending())
            ->sortBy('juz_no')
            ->map(fn (JuzExam $exam): string => "الجزء {$exam->juz_no}")
            ->values()
            ->all();
    }
}
