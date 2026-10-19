<?php

namespace App\Actions\Attendance;

use App\Support\ArabicNoun;
use App\Support\Dates;

/**
 * What came of recording the attendance of many students at once: who
 * was recorded, who was already, and what could not be recorded and why.
 */
final readonly class BulkAttendanceResult
{
    /**
     * @param  list<string>  $recorded  the names of the students recorded
     * @param  list<string>  $alreadyRecorded  the names of those whose attendance was there already
     * @param  list<array{name: string, reason: string}>  $refused  students whose attendance breaks a rule of the day
     * @param  list<string>  $notInCourse  names (or numbers) of students who are not in this course
     * @param  list<string>  $unknownCodes  typed numbers that are no student's card
     * @param  list<string>  $teacherCodes  typed numbers that are teachers' cards: they are scanned on the desk
     */
    public function __construct(
        public string $day,
        public array $recorded,
        public array $alreadyRecorded,
        public array $refused,
        public array $notInCourse,
        public array $unknownCodes,
        public array $teacherCodes,
    ) {}

    public function hasProblems(): bool
    {
        return $this->refused !== [] || $this->notInCourse !== [] || $this->unknownCodes !== [] || $this->teacherCodes !== [];
    }

    /** "سُجّل حضور 12 طالباً ليوم 07/10/2026" */
    public function title(): string
    {
        $day = Dates::show($this->day);

        return $this->recorded === []
            ? "لم يُسجَّل حضور أي طالب جديد ليوم {$day}"
            : 'سُجّل حضور '.ArabicNoun::student()->count(count($this->recorded))." ليوم {$day}";
    }

    /**
     * Everything that was not simply recorded, one line each.
     *
     * @return list<string>
     */
    public function details(): array
    {
        $lines = [];

        if ($this->alreadyRecorded !== []) {
            $lines[] = 'حضورهم مسجَّل مسبقاً: '.implode('، ', $this->alreadyRecorded);
        }

        foreach ($this->refused as $refusal) {
            $lines[] = "لم يُسجَّل {$refusal['name']}: {$refusal['reason']}";
        }

        if ($this->notInCourse !== []) {
            $lines[] = 'ليسوا في هذه الدورة: '.implode('، ', $this->notInCourse);
        }

        if ($this->unknownCodes !== []) {
            $lines[] = 'أرقام لا تعود إلى بطاقة طالب: '.implode('، ', $this->unknownCodes);
        }

        if ($this->teacherCodes !== []) {
            $lines[] = 'بطاقات أساتذة (يُسجَّل حضورهم من صفحة المسح): '.implode('، ', $this->teacherCodes);
        }

        return $lines;
    }
}
