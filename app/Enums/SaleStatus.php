<?php

namespace App\Enums;

/**
 * Every outcome of scanning a card at the sales desk of a points market.
 *
 * Selling never throws for an expected situation: the desk needs a result
 * it can colour and explain, not an exception.
 */
enum SaleStatus: string
{
    case Sold = 'sold';
    case BalanceShown = 'balance_shown';
    case UnknownCard = 'unknown_card';
    case TeacherCard = 'teacher_card';
    case OtherCourse = 'other_course';
    case InactiveEnrollment = 'inactive_enrollment';
    case MarketClosed = 'market_closed';
    case ItemUnavailable = 'item_unavailable';
    case OutOfStock = 'out_of_stock';
    case NotEnoughPoints = 'not_enough_points';

    public function isSuccess(): bool
    {
        return $this === self::Sold;
    }

    /** Nothing was asked for: the student's balance is shown. */
    public function isInformation(): bool
    {
        return $this === self::BalanceShown;
    }

    public function isError(): bool
    {
        return ! $this->isSuccess() && ! $this->isInformation();
    }

    public function label(): string
    {
        return match ($this) {
            self::Sold => 'تم الشراء',
            self::BalanceShown => 'رصيد الطالب',
            self::UnknownCard => 'بطاقة غير معروفة',
            self::TeacherCard => 'هذه بطاقة أستاذ، والشراء للطلاب',
            self::OtherCourse => 'البطاقة لدورة غير دورة هذا السوق',
            self::InactiveEnrollment => 'هذا الطالب غير نشط في الدورة',
            self::MarketClosed => 'السوق مغلق',
            self::ItemUnavailable => 'في السلة بضاعة لم تعد معروضة',
            self::OutOfStock => 'الكمية المتبقية لا تكفي',
            self::NotEnoughPoints => 'رصيد الطالب لا يكفي',
        };
    }
}
