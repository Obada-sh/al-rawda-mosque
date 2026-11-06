<?php

namespace App\Actions\Points;

use App\Enums\PointsLookupStatus;
use App\Enums\PointSource;
use App\Models\Enrollment;
use App\Models\PointTransaction;
use App\Services\Market\Balance;
use App\Support\Dates;

/**
 * The points of the student whose card was scanned at the points desk, in
 * the course of the card: what they earned (and from what), what they
 * spent at the course's markets, what is left, and their latest lines.
 * For any other card, only the status.
 */
final readonly class CardPoints
{
    /** The order the sources of points are told in. */
    public const SOURCE_ORDER = [PointSource::Recitation, PointSource::Attendance, PointSource::Activity, PointSource::Manual];

    /**
     * @param  Enrollment|null  $enrollment  with its student, circle and course, when the card is a student's
     * @param  Balance|null  $balance  earned, spent and left, in the card's course
     * @param  array<string, int>  $bySource  PointSource value => points: only the sources the student has lines of, in the order of SOURCE_ORDER
     * @param  list<PointTransaction>  $latest  the student's latest lines, newest first
     */
    public function __construct(
        public PointsLookupStatus $status,
        public ?Enrollment $enrollment = null,
        public ?Balance $balance = null,
        public array $bySource = [],
        public array $latest = [],
    ) {}

    /**
     * When the student's points can no longer grow in this course: they
     * left it, their file is archived, or the course is over. Null while
     * they are a student of a running course.
     */
    public function notice(): ?string
    {
        $enrollment = $this->enrollment;

        return match (true) {
            $enrollment === null => null,
            $enrollment->student->isArchived() => 'ملف الطالب مؤرشف',
            $enrollment->hasLeft() => 'غادر الطالب الدورة في '.Dates::show($enrollment->left_on),
            ! $enrollment->course->isActive() => 'الدورة '.$enrollment->course->status->label(),
            default => null,
        };
    }
}
