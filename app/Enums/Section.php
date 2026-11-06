<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/**
 * The two sections of the system: the mosque's Quran courses, and the
 * English courses. Each has its own courses, teachers, attendance and
 * money; the students are shared, and each section lists its own.
 */
enum Section: string
{
    use HasOptions;

    case Mosque = 'mosque';
    case English = 'english';

    public function label(): string
    {
        return match ($this) {
            self::Mosque => 'دورات المسجد',
            self::English => 'دورة انجليزي',
        };
    }

    /**
     * The column of the students table that says a student belongs to the section.
     */
    public function studentFlag(): string
    {
        return match ($this) {
            self::Mosque => 'in_mosque',
            self::English => 'in_english',
        };
    }

    /**
     * The column of the students table that says the student's profile in
     * the section is complete.
     */
    public function profileFlag(): string
    {
        return match ($this) {
            self::Mosque => 'is_profile_complete',
            self::English => 'english_profile_complete',
        };
    }

    public function other(): self
    {
        return match ($this) {
            self::Mosque => self::English,
            self::English => self::Mosque,
        };
    }

    /**
     * The id of the section's panel.
     */
    public function panelId(): string
    {
        return match ($this) {
            self::Mosque => 'admin',
            self::English => 'english',
        };
    }
}
