<?php

namespace App\Actions\English\Tests;

use App\Exceptions\BusinessRuleViolation;
use App\Http\Requests\English\EnglishTestResultsRequest;
use App\Models\English\EnglishEnrollment;
use App\Models\English\EnglishTest;
use App\Models\English\EnglishTestResult;
use App\Support\Dates;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Records the results of a test, student by student: a mark out of the
 * test's full mark, or absent, with a note. A student left with neither
 * has no result (one given before is removed). Only the students who were
 * in the course on the day of the test can have a result.
 */
final class SaveEnglishTestResults
{
    /**
     * The students who can have a result in the test: those who were in
     * its course on its day, and any who already have one.
     *
     * @return Collection<int, EnglishEnrollment> with their student and hall, by hall then name
     */
    public function candidates(EnglishTest $test): Collection
    {
        $day = $test->held_on->toDateString();

        return EnglishEnrollment::query()
            ->with(['student', 'hall'])
            ->where('english_course_id', $test->english_course_id)
            ->where(fn (Builder $query) => $query
                ->where(fn (Builder $query) => $query
                    ->where('enrolled_on', '<=', $day)
                    ->where(fn (Builder $query) => $query->whereNull('left_on')->orWhere('left_on', '>=', $day)))
                ->orWhereIn('id', $test->results()->select('english_enrollment_id')))
            ->get()
            ->sortBy(fn (EnglishEnrollment $enrollment): array => [$enrollment->hall->name, $enrollment->student->full_name])
            ->values();
    }

    /**
     * @param  array<string, mixed>  $input  marks: enrollment id => {mark, is_absent, notes}
     * @return int how many students have a result now
     *
     * @throws ValidationException
     * @throws BusinessRuleViolation english_test.not_held_yet | english_test.student_not_in_course
     */
    public function handle(EnglishTest $test, array $input): int
    {
        $data = EnglishTestResultsRequest::validateData(['marks' => $input['marks'] ?? []], ['max_mark' => $test->max_mark]);

        if ($test->held_on->isAfter(Dates::today())) {
            throw BusinessRuleViolation::because('english_test.not_held_yet', 'لم يحن موعد الامتحان بعد: تُسجَّل العلامات من يومه.');
        }

        $allowed = $this->candidates($test)->modelKeys();

        foreach (array_keys($data['marks']) as $enrollmentId) {
            if (! in_array((int) $enrollmentId, $allowed, true)) {
                throw BusinessRuleViolation::because('english_test.student_not_in_course', 'أحد الطلاب ليس في الدورة يوم الامتحان.');
            }
        }

        DB::transaction(function () use ($test, $data): void {
            foreach ($data['marks'] as $enrollmentId => $row) {
                $absent = (bool) ($row['is_absent'] ?? false);
                $mark = $row['mark'] ?? null;
                $key = ['english_test_id' => $test->getKey(), 'english_enrollment_id' => (int) $enrollmentId];

                if (! $absent && $mark === null) {
                    EnglishTestResult::query()->where($key)->delete();

                    continue;
                }

                EnglishTestResult::query()->updateOrCreate($key, [
                    'mark' => $absent ? null : (string) $mark,
                    'is_absent' => $absent,
                    'notes' => $row['notes'] ?? null,
                ]);
            }
        });

        return $test->results()->count();
    }
}
