<?php

namespace App\Actions\English\Attendance;

use App\Actions\Attendance\BulkAttendanceResult;
use App\Exceptions\BusinessRuleViolation;
use App\Http\Requests\English\EnglishBulkAttendanceRequest;
use App\Models\English\EnglishEnrollment;
use App\Models\Enrollment;
use App\Models\TeacherCard;
use App\Support\Dates;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Records the attendance of many students of an English course on one
 * day, chosen by name or by the numbers of their cards. Each student is
 * recorded by RecordEnglishManualAttendance, by its rules, so one student
 * who cannot be recorded never stops the others: the result says who was
 * recorded and why the others were not.
 */
final class RecordEnglishBulkAttendance
{
    /** The digits of a card (see ScanAttendanceRequest). */
    private const CARD_CODE = '/^[0-9]{6,20}$/';

    public function __construct(private readonly RecordEnglishManualAttendance $manual) {}

    /**
     * @param  array<string, mixed>  $input
     *
     * @throws ValidationException
     */
    public function handle(array $input): BulkAttendanceResult
    {
        $data = EnglishBulkAttendanceRequest::validateData($input);

        $courseId = (int) $data['english_course_id'];
        $day = Dates::day($data['attended_on']);

        $notInCourse = [];
        $unknownCodes = [];

        /** @var array<int, EnglishEnrollment> $students keyed by enrollment id: a student named and typed counts once */
        $students = [];

        foreach ($this->chosen($data['english_enrollment_ids'] ?? []) as $enrollment) {
            if ($enrollment->english_course_id === $courseId) {
                $students[$enrollment->getKey()] = $enrollment;
            } else {
                $notInCourse[] = $enrollment->student->full_name;
            }
        }

        $text = (string) ($data['card_codes'] ?? '');
        preg_match_all('/[0-9]+/', $text, $numbers);
        $codes = array_values(array_unique(array_filter($numbers[0], fn (string $number): bool => preg_match(self::CARD_CODE, $number) === 1)));

        $byCode = EnglishEnrollment::query()->with('student')->whereIn('card_code', $codes)->get()->keyBy('card_code');
        $mosqueCodes = [
            ...Enrollment::query()->whereIn('card_code', $codes)->pluck('card_code')->all(),
            ...TeacherCard::query()->whereIn('card_code', $codes)->pluck('card_code')->all(),
        ];

        foreach ($codes as $code) {
            $enrollment = $byCode->get($code);

            if ($enrollment === null) {
                if (in_array($code, $mosqueCodes, true)) {
                    $notInCourse[] = "بطاقة من دورات المسجد ({$code})";
                } else {
                    $unknownCodes[] = $code;
                }
            } elseif ($enrollment->english_course_id !== $courseId) {
                $notInCourse[] = "{$enrollment->student->full_name} ({$code})";
            } else {
                $students[$enrollment->getKey()] = $enrollment;
            }
        }

        // A number too short or too long to be a card is said too.
        foreach (array_unique($numbers[0]) as $number) {
            if (preg_match(self::CARD_CODE, $number) !== 1) {
                $unknownCodes[] = $number;
            }
        }

        $recorded = [];
        $alreadyRecorded = [];
        $refused = [];

        usort($students, fn (EnglishEnrollment $a, EnglishEnrollment $b): int => [$a->student->full_name, $a->getKey()] <=> [$b->student->full_name, $b->getKey()]);

        foreach ($students as $enrollment) {
            $name = $enrollment->student->full_name;

            try {
                $this->manual->handle(['english_enrollment_id' => $enrollment->getKey(), 'attended_on' => $day]);
                $recorded[] = $name;
            } catch (BusinessRuleViolation $violation) {
                if ($violation->rule === 'attendance.duplicate') {
                    $alreadyRecorded[] = $name;
                } else {
                    $refused[] = ['name' => $name, 'reason' => $violation->getMessage()];
                }
            }
        }

        return new BulkAttendanceResult($day, $recorded, $alreadyRecorded, $refused, $notInCourse, array_values($unknownCodes), []);
    }

    /**
     * @param  array<int, mixed>  $ids
     * @return Collection<int, EnglishEnrollment>
     */
    private function chosen(array $ids): Collection
    {
        if ($ids === []) {
            return collect();
        }

        return EnglishEnrollment::query()->with('student')->whereKey(array_map('intval', $ids))->get();
    }
}
