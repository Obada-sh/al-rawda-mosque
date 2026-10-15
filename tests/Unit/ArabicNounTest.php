<?php

namespace Tests\Unit;

use App\Support\ArabicNoun;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class ArabicNounTest extends TestCase
{
    public function test_one_and_two_are_written_without_a_digit(): void
    {
        $this->assertSame('صفحة واحدة', ArabicNoun::page()->count(1));
        $this->assertSame('صفحتان', ArabicNoun::page()->count(2));
        $this->assertSame('نقطة واحدة', ArabicNoun::point()->count(1));
        $this->assertSame('نقطتان', ArabicNoun::point()->count(2));
    }

    public function test_three_to_ten_take_the_plural_and_the_rest_the_singular(): void
    {
        $this->assertSame('3 صفحات', ArabicNoun::page()->count(3));
        $this->assertSame('10 صفحات', ArabicNoun::page()->count(10));
        $this->assertSame('11 صفحة', ArabicNoun::page()->count(11));
        $this->assertSame('24 صفحة', ArabicNoun::page()->count(24));
        $this->assertSame('99 نقطة', ArabicNoun::point()->count(99));
        $this->assertSame('0 صفحة', ArabicNoun::page()->count(0));
    }

    public function test_after_the_hundreds_the_rule_follows_the_last_two_digits(): void
    {
        $this->assertSame('100 صفحة', ArabicNoun::page()->count(100));
        $this->assertSame('101 صفحة', ArabicNoun::page()->count(101));
        $this->assertSame('102 صفحة', ArabicNoun::page()->count(102));
        $this->assertSame('103 صفحات', ArabicNoun::page()->count(103));
        $this->assertSame('110 نقاط', ArabicNoun::point()->count(110));
        $this->assertSame('111 نقطة', ArabicNoun::point()->count(111));
    }

    public function test_thousands_are_separated_by_a_comma(): void
    {
        $this->assertSame('1,250 نقطة', ArabicNoun::point()->count(1250));
        $this->assertSame('2,003 نقاط', ArabicNoun::point()->count(2003));
        $this->assertSame('1,250', ArabicNoun::digits(1250));
    }

    public function test_the_noun_alone_follows_its_number(): void
    {
        $forms = array_map(fn (int $number): string => ArabicNoun::page()->after($number), [1, 2, 3, 10, 11, 100, 103]);

        $this->assertSame(['صفحة', 'صفحتان', 'صفحات', 'صفحات', 'صفحة', 'صفحة', 'صفحات'], $forms);
    }

    public function test_courses_circles_and_years_are_counted_the_same_way(): void
    {
        $this->assertSame(['دورة واحدة', 'دورتان', '3 دورات', '12 دورة'], array_map(fn (int $number): string => ArabicNoun::course()->count($number), [1, 2, 3, 12]));
        $this->assertSame(['حلقة واحدة', 'حلقتان', '7 حلقات', '15 حلقة'], array_map(fn (int $number): string => ArabicNoun::circle()->count($number), [1, 2, 7, 15]));
        $this->assertSame(['سنة واحدة', 'سنتان', '9 سنوات', '16 سنة'], array_map(fn (int $number): string => ArabicNoun::year()->count($number), [1, 2, 9, 16]));
    }

    public function test_a_negative_number_is_not_a_count(): void
    {
        $this->expectException(InvalidArgumentException::class);

        ArabicNoun::point()->count(-3);
    }
}
