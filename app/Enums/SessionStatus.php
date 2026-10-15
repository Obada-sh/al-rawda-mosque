<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum SessionStatus: string
{
    use HasOptions;

    case Held = 'held';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Held => 'منعقدة',
            self::Cancelled => 'ملغاة',
        };
    }
}
