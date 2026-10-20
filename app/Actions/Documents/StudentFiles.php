<?php

namespace App\Actions\Documents;

/**
 * The files of many students, printed together, each on pages of its own.
 */
final readonly class StudentFiles
{
    /** Every student in the system. */
    public const ALL = 'all';

    /** The students of a course, now. */
    public const COURSE = 'course';

    /** The students of a circle, now. */
    public const CIRCLE = 'circle';

    /** The students ticked in the list. */
    public const CHOSEN = 'chosen';

    public const SCOPES = [self::ALL, self::COURSE, self::CIRCLE, self::CHOSEN];

    /**
     * @param  string  $scope  whose files these are, in Arabic ("طلاب دورة الخريف")
     * @param  string|null  $onlyCourse  the name of the one course each file holds; null when every course of each student is in it
     * @param  list<StudentFile>  $files  by student name
     */
    public function __construct(
        public string $scope,
        public ?string $onlyCourse,
        public array $files,
    ) {}
}
