<?php

namespace App\Filament\Support\Presenters;

use App\Enums\ExamGrade;
use App\Enums\ExamStatus;

/**
 * How juz exams are coloured in the panel.
 */
final class ExamPresenter
{
    public static function statusColor(ExamStatus $status): string
    {
        return match ($status) {
            ExamStatus::Pending => 'warning',
            ExamStatus::Done => 'success',
        };
    }

    public static function gradeColor(?ExamGrade $grade): string
    {
        return match ($grade) {
            ExamGrade::Excellent, ExamGrade::VeryGood => 'success',
            ExamGrade::Good => 'info',
            ExamGrade::Acceptable => 'warning',
            ExamGrade::Retake => 'danger',
            null => 'gray',
        };
    }
}
