<?php

namespace App\Actions\Attendance;

use App\Exceptions\BusinessRuleViolation;
use App\Http\Requests\Attendance\BulkAttendanceRequest;
use App\Models\Enrollment;
use App\Models\TeacherCard;
use App\Support\Dates;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Records the attendance of many students of a course on one day, chosen
 * by name or by the numbers of their cards (typed, or scanned one after
 * the other into the box). Each student is recorded by
 * RecordManualAttendance, by its rules and with its points, so one
 * student who cannot be recorded never stops the others: the result says
 * who was recorded and why the others were not.
 */
final class RecordBulkAttendance
{
    /** The digits of a card (see ScanAttendanceRequest). */
    private const CARD_CODE = '/^[0-9]{6,20}$/';

    public function __construct(private readonly RecordManualAttendance $manual) {}

    /**
     * @param  array<string, mixed>  $input
     *
     * @throws ValidationException
     */
    public function handle(array $input): BulkAttendanceResult
    {
        $data = BulkAttendanceRequest::validateData($input);

        $courseId = (int) $data['course_id'];
        $day = Dates::day($data['attended_on']);

        $notInCourse = [];
        $unknownCodes = [];
        $teacherCodes = [];

        /** @var array<int, Enrollment> $students keyed by enrollment id: a student named and typed counts once */
        $students = [];

        foreach ($this->chosen($data['enrollment_ids'] ?? []) as $enrollment) {
            if ($enrollment->course_id === $courseId) {
                $students[$enrollment->getKey()] = $enrollment;
            } else {
                $notInCourse[] = $enrollment->student->full_name;
            }
        }

        $codes = self::codes((string) ($data['card_codes'] ?? ''));
        $byCode = Enrollment::query()->with('student')->whereIn('card_code', $codes)->get()->keyBy('card_code');
        $teachers = TeacherCard::query()->whereIn('card_code', $codes)->pluck('card_code')->all();

        foreach ($codes as $code) {
            $enrollment = $byCode->get($code);

            if (in_array($code, $teachers, true)) {
                $teacherCodes[] = $code;
            } elseif ($enrollment === null) {
                $unknownCodes[] = $code;
            } elseif ($enrollment->course_id !== $courseId) {
                $notInCourse[] = "{$enrollment->student->full_name} ({$code})";
            } else {
                $students[$enrollment->getKey()] = $enrollment;
            }
        }

        // A number too short or too long to be a card is said too.
        foreach (self::notCards((string) ($data['card_codes'] ?? '')) as $number) {
            $unknownCodes[] = $number;
        }

        $recorded = [];
        $alreadyRecorded = [];
        $refused = [];

        foreach (self::byName($students) as $enrollment) {
            $name = $enrollment->student->full_name;

            try {
                $this->manual->handle(['enrollment_id' => $enrollment->getKey(), 'attended_on' => $day]);
                $recorded[] = $name;
            } catch (BusinessRuleViolation $violation) {
                if ($violation->rule === 'attendance.duplicate') {
                    $alreadyRecorded[] = $name;
                } else {
                    $refused[] = ['name' => $name, 'reason' => $violation->getMessage()];
                }
            }
        }

        return new BulkAttendanceResult($day, $recorded, $alreadyRecorded, $refused, $notInCourse, $unknownCodes, $teacherCodes);
    }

    /**
     * @param  array<int, mixed>  $ids
     * @return Collection<int, Enrollment>
     */
    private function chosen(array $ids): Collection
    {
        if ($ids === []) {
            return collect();
        }

        return Enrollment::query()->with('student')->whereKey(array_map('intval', $ids))->get();
    }

    /**
     * The card numbers of the text, each once: one a line, or with spaces,
     * commas or anything else between them.
     *
     * @return list<string>
     */
    private static function codes(string $text): array
    {
        preg_match_all('/[0-9]+/', $text, $numbers);

        return array_values(array_unique(array_filter($numbers[0], fn (string $number): bool => preg_match(self::CARD_CODE, $number) === 1)));
    }

    /**
     * The numbers of the text that cannot be a card: too short or too long.
     *
     * @return list<string>
     */
    private static function notCards(string $text): array
    {
        preg_match_all('/[0-9]+/', $text, $numbers);

        return array_values(array_unique(array_filter($numbers[0], fn (string $number): bool => preg_match(self::CARD_CODE, $number) !== 1)));
    }

    /**
     * @param  array<int, Enrollment>  $students
     * @return list<Enrollment>
     */
    private static function byName(array $students): array
    {
        usort($students, fn (Enrollment $a, Enrollment $b): int => [$a->student->full_name, $a->getKey()] <=> [$b->student->full_name, $b->getKey()]);

        return $students;
    }
}
