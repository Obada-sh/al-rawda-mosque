<?php

namespace App\Actions\English\Cards;

/**
 * Everything printed on one attendance card of the English section: the
 * design of the course's level (its picture, or the plain card when the
 * level has none) with the student's photo, name, hall and code over it.
 */
final readonly class EnglishCard
{
    /**
     * @param  string  $qrSvg  the code as a QR drawing
     * @param  string|null  $photo  the file of the student's photo, when they have one
     * @param  string|null  $background  the file of the level's design, when it has one
     */
    public function __construct(
        public string $name,
        public string $hallName,
        public string $levelName,
        public string $courseName,
        public string $code,
        public string $qrSvg,
        public int $studentId,
        public int $levelId,
        public ?string $photo = null,
        public ?string $background = null,
    ) {}
}
