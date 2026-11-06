<?php

namespace App\Actions\English\Courses;

use App\Exceptions\BusinessRuleViolation;
use App\Models\English\EnglishCourse;
use Illuminate\Support\Facades\DB;

/**
 * Deletes a course created by mistake: one with no student, class day,
 * test or paper. Its halls and weekly class days go with it. A course that
 * was used is finished instead.
 */
final class DeleteEnglishCourse
{
    /**
     * @throws BusinessRuleViolation english_course.has_records
     */
    public function handle(EnglishCourse $course): void
    {
        $used = $course->enrollments()->exists()
            || $course->sessions()->exists()
            || $course->tests()->exists()
            || $course->papers()->exists();

        if ($used) {
            throw BusinessRuleViolation::because(
                'english_course.has_records',
                'لا يمكن حذف دورة لها طلاب أو سجلات. يمكنك إنهاؤها بدلاً من ذلك.',
            );
        }

        DB::transaction(function () use ($course): void {
            $course->halls()->delete();
            // The weekly class days go with the course (cascade).
            $course->delete();
        });
    }
}
