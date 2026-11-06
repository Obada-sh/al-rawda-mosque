<?php

namespace App\Actions\Documents;

/**
 * What the list of a circle's students shows: the records of the course
 * only, or of every course the students were ever in; and which columns,
 * next to the names.
 *
 * In the address of the sheet each column is a flag (pages=0 hides the
 * pages); a column that is not named is shown, so the plain address of a
 * sheet shows everything of the course. The notes are the exception: they
 * are written by the admin about the student, so they are printed only
 * when asked (notes=1), and the window offers them unticked.
 */
final readonly class SheetOptions
{
    /** The records of the course of the circle only. */
    public const THIS_COURSE = 'course';

    /** The students' records in every course, since they joined the system. */
    public const ALL_COURSES = 'all';

    /** The columns the admin can show or hide, in the order of the table. */
    public const COLUMNS = ['phones', 'pages', 'exams', 'attendance', 'notes'];

    /** The columns ticked when the window opens: every one but the notes. */
    public const DEFAULT_COLUMNS = ['phones', 'pages', 'exams', 'attendance'];

    public function __construct(
        public bool $allCourses = false,
        public bool $phones = true,
        public bool $pages = true,
        public bool $exams = true,
        public bool $attendance = true,
        public bool $notes = false,
    ) {}

    /**
     * @param  array<string, mixed>  $data  validated by CircleSheetRequest
     */
    public static function from(array $data): self
    {
        $shown = fn (string $column): bool => ! array_key_exists($column, $data) || $data[$column] === null || (bool) $data[$column];

        return new self(
            allCourses: ($data['history'] ?? self::THIS_COURSE) === self::ALL_COURSES,
            phones: $shown('phones'),
            pages: $shown('pages'),
            exams: $shown('exams'),
            attendance: $shown('attendance'),
            notes: (bool) ($data['notes'] ?? false),
        );
    }

    /**
     * The options chosen in a window: the history, and the columns ticked.
     *
     * @param  list<string>  $columns  some of COLUMNS
     */
    public static function chosen(string $history, array $columns): self
    {
        return new self(
            allCourses: $history === self::ALL_COURSES,
            phones: in_array('phones', $columns, true),
            pages: in_array('pages', $columns, true),
            exams: in_array('exams', $columns, true),
            attendance: in_array('attendance', $columns, true),
            notes: in_array('notes', $columns, true),
        );
    }

    /**
     * The options as they are written in the address of the sheet.
     *
     * @return array<string, string>
     */
    public function query(): array
    {
        return [
            'history' => $this->allCourses ? self::ALL_COURSES : self::THIS_COURSE,
            'phones' => $this->phones ? '1' : '0',
            'pages' => $this->pages ? '1' : '0',
            'exams' => $this->exams ? '1' : '0',
            'attendance' => $this->attendance ? '1' : '0',
            'notes' => $this->notes ? '1' : '0',
        ];
    }

    /**
     * How many columns the table of the sheet has, the number and the name included.
     */
    public function columnCount(): int
    {
        return 2 + ($this->phones ? 2 : 0) + (int) $this->pages + (int) $this->exams + (int) $this->attendance + (int) $this->notes;
    }
}
