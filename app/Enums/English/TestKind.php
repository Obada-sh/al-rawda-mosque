<?php

namespace App\Enums\English;

use App\Enums\Concerns\HasOptions;

/**
 * What an English test was: an exam, or a recitation (تسميع) heard in class.
 */
enum TestKind: string
{
    use HasOptions;

    case Exam = 'exam';
    case Recitation = 'recitation';

    public function label(): string
    {
        return match ($this) {
            self::Exam => 'امتحان',
            self::Recitation => 'تسميع',
        };
    }
}
