<?php

namespace App\Services\English;

use App\Enums\English\PaymentState;
use App\Models\English\EnglishEnrollment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * The report of the students' payments: for each student of a course (or
 * of every course) the price due, what was paid and what remains; and the
 * same added up by course, and for everything.
 */
final class EnglishMoneyReport
{
    public function __construct(private readonly EnglishBalances $balances) {}

    /**
     * @return Collection<int, EnglishStudentBalance> by course (newest first), then by student name
     */
    public function students(EnglishBalanceFilter $filter): Collection
    {
        $enrollments = EnglishEnrollment::query()
            ->with(['student', 'course', 'hall'])
            ->when($filter->courseId, fn (Builder $query, int $courseId) => $query->where('english_course_id', $courseId))
            ->when($filter->hallId, fn (Builder $query, int $hallId) => $query->where('english_hall_id', $hallId))
            ->when(! $filter->withLeft, fn (Builder $query) => $query->whereNull('left_on'))
            ->get();

        $balances = $this->balances->ofMany($enrollments);

        return $enrollments
            ->sortBy(fn (EnglishEnrollment $enrollment): array => [
                -$enrollment->course->start_date->getTimestamp(),
                $enrollment->english_course_id,
                $enrollment->student->full_name,
                $enrollment->getKey(),
            ])
            ->map(fn (EnglishEnrollment $enrollment): EnglishStudentBalance => new EnglishStudentBalance(
                enrollmentId: $enrollment->getKey(),
                studentId: $enrollment->student_id,
                studentName: $enrollment->student->full_name,
                courseId: $enrollment->english_course_id,
                courseName: $enrollment->course->name,
                hallName: $enrollment->hall->name,
                balance: $balances[$enrollment->getKey()],
                priceNote: $enrollment->price_note,
                hasLeft: $enrollment->hasLeft(),
            ))
            ->filter(fn (EnglishStudentBalance $line): bool => $filter->state === null || $line->balance->state() === $filter->state)
            ->values();
    }

    /**
     * @param  Collection<int, EnglishStudentBalance>  $students
     * @return Collection<int, EnglishCourseMoney> in the order of the lines
     */
    public function byCourse(Collection $students): Collection
    {
        return $students
            ->groupBy(fn (EnglishStudentBalance $line): int => $line->courseId)
            ->map(fn (Collection $lines): EnglishCourseMoney => $this->sum($lines, $lines->first()->courseId, $lines->first()->courseName))
            ->values();
    }

    /**
     * @param  Collection<int, EnglishStudentBalance>  $students
     */
    public function total(Collection $students): EnglishCourseMoney
    {
        return $this->sum($students, null, 'المجموع');
    }

    /**
     * @param  Collection<int, EnglishStudentBalance>  $lines
     */
    private function sum(Collection $lines, ?int $courseId, string $name): EnglishCourseMoney
    {
        $count = fn (PaymentState $state): int => $lines->filter(fn (EnglishStudentBalance $line): bool => $line->balance->state() === $state)->count();

        return new EnglishCourseMoney(
            courseId: $courseId,
            courseName: $name,
            students: $lines->count(),
            due: (int) $lines->sum(fn (EnglishStudentBalance $line): int => $line->balance->due),
            paid: (int) $lines->sum(fn (EnglishStudentBalance $line): int => $line->balance->paid),
            remaining: (int) $lines->sum(fn (EnglishStudentBalance $line): int => $line->balance->remaining()),
            paidInFull: $count(PaymentState::Paid),
            partly: $count(PaymentState::Partial),
            unpaid: $count(PaymentState::Unpaid),
        );
    }
}
