<?php

namespace App\Actions\Documents;

/**
 * A student on the sheet of their circle.
 */
final readonly class CircleSheetLine
{
    public function __construct(
        public string $name,
        public ?string $phone,
        public ?string $guardianPhone,
        /** Pages memorized for the first time in this course (in every circle of it). */
        public int $newPages,
        /** What the student's attendance card of this course carries. */
        public string $cardCode,
        /** The QR of the card code, when the sheet shows the codes. */
        public ?string $qrSvg = null,
    ) {}
}
