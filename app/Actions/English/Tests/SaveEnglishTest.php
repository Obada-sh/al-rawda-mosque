<?php

namespace App\Actions\English\Tests;

use App\Exceptions\BusinessRuleViolation;
use App\Http\Requests\English\EnglishTestRequest;
use App\Models\English\EnglishCourse;
use App\Models\English\EnglishTest;
use App\Support\Dates;
use Illuminate\Validation\ValidationException;

/**
 * Adds an exam or a recitation to an English course, or corrects one. Its
 * day falls inside the course; its full mark is never below a mark that
 * was already given.
 */
final class SaveEnglishTest
{
    /**
     * @param  array<string, mixed>  $input
     *
     * @throws ValidationException
     * @throws BusinessRuleViolation english_test.outside_course | english_test.mark_above_max
     */
    public function handle(EnglishCourse $course, array $input, ?EnglishTest $test = null): EnglishTest
    {
        $data = EnglishTestRequest::validateData($input);
        $day = Dates::parse($data['held_on']);

        if (! $course->covers($day)) {
            throw BusinessRuleViolation::because('english_test.outside_course', 'تاريخ الامتحان خارج فترة الدورة.');
        }

        if ($test !== null) {
            $highest = $test->results()->max('mark');

            if ($highest !== null && (float) $highest > (int) $data['max_mark']) {
                throw BusinessRuleViolation::because(
                    'english_test.mark_above_max',
                    'أُعطي طالب علامة '.rtrim(rtrim((string) $highest, '0'), '.').'، وهي أكبر من العلامة الكاملة الجديدة.',
                );
            }

            if ($test->results()->exists() && $day->isAfter(Dates::today())) {
                throw BusinessRuleViolation::because('english_test.results_before_day', 'للامتحان علامات مسجَّلة، فلا يكون تاريخه في المستقبل.');
            }
        }

        $test ??= new EnglishTest(['english_course_id' => $course->getKey()]);
        $test->fill([
            'title' => $data['title'],
            'kind' => $data['kind'],
            'held_on' => $day->toDateString(),
            'max_mark' => (int) $data['max_mark'],
            'notes' => $data['notes'] ?? null,
        ])->save();

        return $test;
    }
}
