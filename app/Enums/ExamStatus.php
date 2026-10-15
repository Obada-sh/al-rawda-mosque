<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum ExamStatus: string
{
    use HasOptions;

    case Pending = 'pending';
    case Done = 'done';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'بانتظار السبر',
            self::Done => 'تم',
        };
    }
}
