<?php

namespace App\Actions\Documents;

use App\Models\Student;
use App\Support\PageRanges;

/**
 * A student on a sheet: the sheet of their circle, or the sheet of every
 * student of the mosque; with their records in one course, or in every
 * course when the sheet asks for them (see SheetOptions).
 */
final readonly class SheetLine
{
    /**
     * @param  int  $newPages  pages memorized for the first time: in the course (in every circle of it), or in the system
     * @param  string  $pageRanges  the same pages, written short ("1–20، 25"); empty when there are none
     * @param  int|null  $priorPages  with every course: the pages memorized before joining the system, when known
     * @param  list<SheetExam>  $exams  by juz
     * @param  int  $attended  sessions attended
     * @param  int  $sessions  sessions held while the student was in the course(s)
     * @param  string  $cardCode  what the student's attendance card of the circle's course carries; empty on the mosque's sheet
     * @param  string|null  $qrSvg  the QR of the card code, when the sheet shows the codes
     * @param  string|null  $circle  on the mosque's sheet: the student's circle(s); null when they are in none now
     * @param  bool  $archived  on the mosque's sheet: the student's file is archived
     */
    public function __construct(
        public string $name,
        public ?string $phone,
        public ?string $guardianPhone,
        public int $newPages,
        public string $pageRanges,
        public ?int $priorPages,
        public array $exams,
        public int $attended,
        public int $sessions,
        public string $cardCode = '',
        public ?string $qrSvg = null,
        public ?string $circle = null,
        public bool $archived = false,
    ) {}

    /**
     * The line of a student with their record; the rest as the sheet needs it.
     */
    public static function of(
        Student $student,
        StudentRecord $record,
        string $cardCode = '',
        ?string $qrSvg = null,
        ?string $circle = null,
    ): self {
        return new self(
            name: $student->full_name,
            phone: $student->phone,
            guardianPhone: $student->guardian_phone,
            newPages: $record->newPages,
            pageRanges: PageRanges::of($record->pages),
            priorPages: $record->priorPages,
            exams: $record->exams,
            attended: $record->attended,
            sessions: $record->sessions,
            cardCode: $cardCode,
            qrSvg: $qrSvg,
            circle: $circle,
            archived: $student->isArchived(),
        );
    }

    /**
     * The juz the student passed (a retake is not a pass).
     */
    public function examsPassed(): int
    {
        return count(array_filter($this->exams, fn (SheetExam $exam): bool => $exam->isPassed()));
    }
}
