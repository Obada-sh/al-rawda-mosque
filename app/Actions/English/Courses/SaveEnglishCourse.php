<?php

namespace App\Actions\English\Courses;

use App\Exceptions\BusinessRuleViolation;
use App\Http\Requests\English\EnglishCourseRequest;
use App\Models\English\EnglishCourse;
use App\Models\English\EnglishCourseSchedule;
use App\Models\English\EnglishPayment;
use App\Support\Dates;
use App\Support\Money;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Creates or updates an English course together with its weekly class days.
 */
final class SaveEnglishCourse
{
    private const COURSE_FIELDS = ['name', 'english_level_id', 'price', 'start_date', 'end_date', 'status'];

    /**
     * @param  array<string, mixed>  $input
     *
     * @throws ValidationException
     * @throws BusinessRuleViolation english_course.records_before_start | english_course.records_after_end
     *                               | english_course.price_below_paid
     */
    public function handle(array $input, ?EnglishCourse $course = null): EnglishCourse
    {
        $data = EnglishCourseRequest::validateData($input);

        $data['start_date'] = Dates::day($data['start_date']);
        $data['end_date'] = isset($data['end_date']) ? Dates::day($data['end_date']) : null;
        $data['price'] = (int) $data['price'];

        return DB::transaction(function () use ($data, $course): EnglishCourse {
            if ($course !== null) {
                $course = EnglishCourse::query()->lockForUpdate()->findOrFail($course->getKey());

                $this->assertDatesKeepExistingRecords($course, $data['start_date'], $data['end_date']);
                $this->assertPriceCoversPayments($course, $data['price']);
            }

            $course ??= new EnglishCourse;
            $course->fill(Arr::only($data, self::COURSE_FIELDS))->save();

            $this->syncSchedules($course, $data['schedules']);

            return $course->refresh()->load('schedules');
        });
    }

    /**
     * Changing the dates must not leave class days or tests outside the course.
     */
    private function assertDatesKeepExistingRecords(EnglishCourse $course, string $start, ?string $end): void
    {
        $dates = array_filter([
            $course->sessions()->min('session_date'),
            $course->sessions()->max('session_date'),
            $course->tests()->min('held_on'),
            $course->tests()->max('held_on'),
        ]);

        if ($dates === []) {
            return;
        }

        $days = array_map(fn (mixed $date): string => substr((string) $date, 0, 10), $dates);

        if (min($days) < $start) {
            throw BusinessRuleViolation::because(
                'english_course.records_before_start',
                'توجد سجلات في الدورة قبل تاريخ البداية الجديد ('.Dates::show(min($days)).').',
            );
        }

        if ($end !== null && max($days) > $end) {
            throw BusinessRuleViolation::because(
                'english_course.records_after_end',
                'توجد سجلات في الدورة بعد تاريخ النهاية الجديد ('.Dates::show(max($days)).').',
            );
        }
    }

    /**
     * A student who pays the course's price must never have paid more than
     * the price: the new one is at least what any of them paid.
     */
    private function assertPriceCoversPayments(EnglishCourse $course, int $price): void
    {
        $paidByEach = EnglishPayment::query()
            ->whereIn('english_enrollment_id', $course->enrollments()->whereNull('price')->select('id'))
            ->groupBy('english_enrollment_id')
            ->selectRaw('SUM(amount) AS paid');

        $mostPaid = (int) DB::query()->fromSub($paidByEach, 'paid_by_each')->max('paid');

        if ($mostPaid > $price) {
            throw BusinessRuleViolation::because(
                'english_course.price_below_paid',
                'دفع أحد طلاب الدورة '.Money::pounds($mostPaid).'، وهو أكثر من السعر الجديد.',
            );
        }
    }

    /**
     * @param  list<array{weekday: int|string, start_time: string, end_time: string}>  $schedules
     */
    private function syncSchedules(EnglishCourse $course, array $schedules): void
    {
        $weekdays = array_map(fn (array $row): int => (int) $row['weekday'], $schedules);

        $course->schedules()->whereNotIn('weekday', $weekdays)->delete();

        foreach ($schedules as $row) {
            EnglishCourseSchedule::query()->updateOrCreate(
                ['english_course_id' => $course->getKey(), 'weekday' => (int) $row['weekday']],
                [
                    'start_time' => $this->normalizeTime($row['start_time']),
                    'end_time' => $this->normalizeTime($row['end_time']),
                ],
            );
        }
    }

    private function normalizeTime(string $time): string
    {
        return strlen($time) === 5 ? $time.':00' : $time;
    }
}
