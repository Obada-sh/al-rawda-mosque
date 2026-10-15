<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/**
 * How a juz exam came to exist.
 *
 * Auto exams are opened by the system when a juz becomes complete and are
 * withdrawn again if the recitation that completed it is corrected or deleted
 * before the exam is taken. Manual exams are never touched by the system.
 */
enum ExamOrigin: string
{
    use HasOptions;

    case Auto = 'auto';
    case Manual = 'manual';

    public function label(): string
    {
        return match ($this) {
            self::Auto => 'تلقائي',
            self::Manual => 'يدوي',
        };
    }
}
