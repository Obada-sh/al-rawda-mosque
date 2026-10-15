<?php

namespace App\Actions\Cards;

/**
 * Everything printed on one attendance card: the mosque's card design
 * (resources/views/cards/badge.blade.php) filled with the owner's photo,
 * name and code, and for a student the circle and its logo.
 *
 * A teacher's card has no circle: it shows the photo, the name, the
 * course the card is for (a teacher of two courses has two cards), the
 * mosque's logo and the code. The photo is found through whichever of the
 * two owns the card.
 */
final readonly class AttendanceCard
{
    /**
     * @param  string  $circleName  what the green band under the name says: the student's circle (a teacher's card has no band)
     * @param  string  $qrSvg  the code as a QR drawing, in the colour of the design
     * @param  string|null  $photo  the file of the owner's photo, when they have one
     * @param  string|null  $circleLogo  the file of the circle's logo, when it has one
     * @param  string|null  $courseName  for a teacher's card: the course it is for
     */
    public function __construct(
        public string $name,
        public string $circleName,
        public string $code,
        public string $qrSvg,
        public ?int $studentId = null,
        public ?int $teacherId = null,
        public ?string $photo = null,
        public ?int $circleId = null,
        public ?string $circleLogo = null,
        public ?string $courseName = null,
    ) {}

    public function isTeacher(): bool
    {
        return $this->teacherId !== null;
    }
}
