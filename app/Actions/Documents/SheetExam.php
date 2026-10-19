<?php

namespace App\Actions\Documents;

use App\Enums\ExamGrade;

/**
 * Where a student stands with the exam of one juz: the grade of the last
 * exam taken, or waiting when the last one is still to be taken.
 */
final readonly class SheetExam
{
    /**
     * @param  ExamGrade|null  $grade  null while the exam is waiting
     */
    public function __construct(
        public int $juz,
        public ?ExamGrade $grade,
    ) {}

    public function isPassed(): bool
    {
        return $this->grade !== null && $this->grade !== ExamGrade::Retake;
    }

    /** "30 (ممتاز)", "29 (بانتظار السبر)" */
    public function label(): string
    {
        return $this->juz.' ('.($this->grade?->label() ?? 'بانتظار السبر').')';
    }
}
