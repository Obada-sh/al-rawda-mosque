<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * A counted thing, written the way Arabic counts it: the noun changes
 * with the number in front of it.
 *
 *   1 صفحة واحدة · 2 صفحتان · 3 to 10 صفحات · 11 and more صفحة
 *
 * After the hundreds the rule starts again from the last two digits:
 * 103 صفحات, 111 صفحة.
 */
final readonly class ArabicNoun
{
    private function __construct(
        private string $one,
        private string $two,
        private string $few,
        private string $many,
        private string $exactlyOne,
    ) {}

    public static function page(): self
    {
        return new self('صفحة', 'صفحتان', 'صفحات', 'صفحة', 'صفحة واحدة');
    }

    public static function point(): self
    {
        return new self('نقطة', 'نقطتان', 'نقاط', 'نقطة', 'نقطة واحدة');
    }

    public static function course(): self
    {
        return new self('دورة', 'دورتان', 'دورات', 'دورة', 'دورة واحدة');
    }

    public static function circle(): self
    {
        return new self('حلقة', 'حلقتان', 'حلقات', 'حلقة', 'حلقة واحدة');
    }

    public static function year(): self
    {
        return new self('سنة', 'سنتان', 'سنوات', 'سنة', 'سنة واحدة');
    }

    /**
     * The number together with its noun: "صفحة واحدة", "صفحتان",
     * "7 صفحات", "1,250 نقطة". One and two need no digit in Arabic.
     *
     * @param  int  $number  zero or more
     */
    public function count(int $number): string
    {
        return match ($number) {
            1 => $this->exactlyOne,
            2 => $this->two,
            default => self::digits($number).' '.$this->after($number),
        };
    }

    /**
     * The noun alone, in the form it takes next to the number: for a
     * slide that shows the number and its unit in two different places.
     *
     * @param  int  $number  zero or more
     */
    public function after(int $number): string
    {
        if ($number < 0) {
            throw new InvalidArgumentException('Only what can be counted has a counted form: the number cannot be negative.');
        }

        $lastTwoDigits = $number % 100;

        return match (true) {
            $number === 1 => $this->one,
            $number === 2 => $this->two,
            $lastTwoDigits >= 3 && $lastTwoDigits <= 10 => $this->few,
            default => $this->many,
        };
    }

    /**
     * The number alone, with a comma between the thousands.
     */
    public static function digits(int $number): string
    {
        return number_format($number);
    }
}
