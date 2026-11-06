<?php

namespace App\Enums;

/**
 * What scanning a card at the points desk led to. A card of the mosque
 * shows its student's points; any other card is told apart, never thrown.
 */
enum PointsLookupStatus: string
{
    case Found = 'found';
    case UnknownCard = 'unknown_card';
    case TeacherCard = 'teacher_card';
    case EnglishCard = 'english_card';

    public function isFound(): bool
    {
        return $this === self::Found;
    }

    public function label(): string
    {
        return match ($this) {
            self::Found => 'رصيد الطالب',
            self::UnknownCard => 'بطاقة غير معروفة',
            self::TeacherCard => 'هذه بطاقة أستاذ، والنقاط للطلاب',
            self::EnglishCard => 'هذه بطاقة دورة انجليزي، ولا نقاط في دورات الانجليزي',
        };
    }
}
