<?php

namespace App\Actions\Courses;

use App\Exceptions\BusinessRuleViolation;
use App\Models\Activity;
use App\Models\Course;
use Illuminate\Support\Facades\DB;

/**
 * Deletes a course that was never used (no students, no sessions, no
 * activity of one of its circles, no points market).
 */
final class DeleteCourse
{
    /**
     * @throws BusinessRuleViolation course.has_records
     */
    public function handle(Course $course): void
    {
        $isUsed = $course->enrollments()->exists()
            || $course->sessions()->exists()
            || Activity::query()->whereIn('circle_id', $course->circles()->select('id'))->exists()
            || $course->markets()->exists();

        if ($isUsed) {
            throw BusinessRuleViolation::because(
                'course.has_records',
                'لا يمكن حذف دورة فيها طلاب أو جلسات أو نشاطات أو أسواق نقاط. يمكنك إنهاؤها بدلاً من ذلك.',
            );
        }

        DB::transaction(function () use ($course): void {
            $course->circles()->delete();
            $course->schedules()->delete();
            $course->delete();
        });
    }
}
