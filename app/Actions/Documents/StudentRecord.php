<?php

namespace App\Actions\Documents;

/**
 * What a student did, as a sheet prints it: in one course, or in every
 * course since they joined the system (see SheetRecords).
 */
final readonly class StudentRecord
{
    /**
     * @param  int  $newPages  pages memorized for the first time
     * @param  list<int>  $pages  the same pages, by number
     * @param  int|null  $priorPages  with every course: the pages memorized before joining the system, when known
     * @param  list<SheetExam>  $exams  by juz
     * @param  int  $attended  sessions attended
     * @param  int  $sessions  sessions held while the student was in the course(s)
     */
    public function __construct(
        public int $newPages,
        public array $pages,
        public ?int $priorPages,
        public array $exams,
        public int $attended,
        public int $sessions,
    ) {}
}
