<?php

namespace App\Actions\Attendance;

use App\Enums\ScanStatus;
use App\Http\Requests\Attendance\ScanAttendanceRequest;
use App\Models\TeacherCard;
use Carbon\CarbonInterface;
use Illuminate\Validation\ValidationException;

/**
 * The scan screen's one entry: a scanned card is a teacher's card in a
 * course or a student's (codes are never shared, see
 * NumericCardCodeGenerator), and is recorded as such.
 */
final class ScanCard
{
    public function __construct(
        private readonly RecordScanAttendance $students,
        private readonly RecordTeacherScan $teachers,
    ) {}

    public function handle(?string $cardCode, ?CarbonInterface $at = null): ScanResult
    {
        try {
            $data = ScanAttendanceRequest::validateData(['card_code' => $cardCode]);
        } catch (ValidationException) {
            return new ScanResult(ScanStatus::UnknownCard);
        }

        $teacherCard = TeacherCard::query()->where('card_code', $data['card_code'])->first();

        return $teacherCard === null
            ? $this->students->handle($data['card_code'], $at)
            : $this->teachers->handle($teacherCard, $at);
    }
}
