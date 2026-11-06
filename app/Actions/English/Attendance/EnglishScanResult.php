<?php

namespace App\Actions\English\Attendance;

use App\Enums\ScanStatus;
use App\Models\English\EnglishAttendance;
use App\Models\English\EnglishEnrollment;
use App\Support\Dates;
use Carbon\CarbonInterface;

/**
 * What happened when a card was scanned at the English section's desk,
 * with everything the desk needs to show.
 */
final readonly class EnglishScanResult
{
    /**
     * @param  CarbonInterface|null  $day  the day the attendance is for, when it is not today
     */
    public function __construct(
        public ScanStatus $status,
        public ?EnglishEnrollment $enrollment = null,
        public ?EnglishAttendance $attendance = null,
        public ?CarbonInterface $day = null,
    ) {}

    public function message(): string
    {
        return $this->status->label($this->day === null ? null : Dates::show($this->day));
    }

    public function studentName(): ?string
    {
        return $this->enrollment?->student?->full_name;
    }

    public function hallName(): ?string
    {
        return $this->enrollment?->hall?->name;
    }

    public function courseName(): ?string
    {
        return $this->enrollment?->course?->name;
    }
}
