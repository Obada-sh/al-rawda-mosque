<?php

namespace App\Actions\Documents;

use App\Models\JuzExam;
use App\Models\Student;
use App\Services\Statistics\StudentStanding;
use App\Services\Statistics\StudentStandings;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The records the sheets of students print: what each student did in one
 * course, or in every course since they joined the system.
 *
 * The figures come from the same source as the statistics, so a sheet
 * and the reports always agree.
 */
final class SheetRecords
{
    public function __construct(private readonly StudentStandings $standings) {}

    /**
     * What each student did in the course of their standing.
     *
     * @param  Collection<int, StudentStanding>  $standings
     * @return array<int, StudentRecord> keyed by enrollment id
     */
    public function inCourse(Collection $standings): array
    {
        $enrollmentIds = $standings->pluck('enrollmentId')->all();

        // A page belongs to the recitation that memorized it first; that recitation belongs to a course.
        $pages = DB::table('student_pages')
            ->join('recitations', 'recitations.id', '=', 'student_pages.recitation_id')
            ->whereIn('recitations.enrollment_id', $enrollmentIds)
            ->get(['recitations.enrollment_id', 'student_pages.page_no'])
            ->groupBy('enrollment_id');

        $exams = JuzExam::query()->whereIn('enrollment_id', $enrollmentIds)->orderBy('id')->get()->groupBy('enrollment_id');

        $records = [];

        foreach ($standings as $standing) {
            $records[$standing->enrollmentId] = new StudentRecord(
                newPages: $standing->newPages,
                pages: self::pageNumbers($pages->get($standing->enrollmentId, collect())),
                priorPages: null,
                exams: self::marks($exams->get($standing->enrollmentId, collect())),
                attended: $standing->attended,
                sessions: $standing->sessions,
            );
        }

        return $records;
    }

    /**
     * What each student did since joining the system: every page they
     * memorized, every exam (of any course, or of none), and their
     * attendance in all their courses together.
     *
     * @param  Collection<int, Student>  $students
     * @return array<int, StudentRecord> keyed by student id
     */
    public function sinceJoining(Collection $students): array
    {
        $studentIds = $students->map(fn (Student $student): int => (int) $student->getKey())->values()->all();

        $pages = DB::table('student_pages')->whereIn('student_id', $studentIds)->get(['student_id', 'page_no'])->groupBy('student_id');
        $exams = JuzExam::query()->whereIn('student_id', $studentIds)->orderBy('id')->get()->groupBy('student_id');
        $courses = $this->standings->forStudents($studentIds)->groupBy('studentId');

        $records = [];

        foreach ($students as $student) {
            $id = (int) $student->getKey();
            /** @var Collection<int, StudentStanding> $standings */
            $standings = $courses->get($id, collect());

            $records[$id] = new StudentRecord(
                newPages: (int) $student->recited_pages_count,
                pages: self::pageNumbers($pages->get($id, collect())),
                priorPages: $student->prior_memorized_pages,
                exams: self::marks($exams->get($id, collect())),
                attended: (int) $standings->sum('attended'),
                sessions: (int) $standings->sum('sessions'),
            );
        }

        return $records;
    }

    /**
     * @param  Collection<int, object>  $rows  rows of student_pages
     * @return list<int>
     */
    private static function pageNumbers(Collection $rows): array
    {
        return $rows->pluck('page_no')->map(fn (mixed $page): int => (int) $page)->values()->all();
    }

    /**
     * One mark per juz, in the order of the juz: the grade of its last
     * exam, or waiting when the last one is still to be taken.
     *
     * @param  Collection<int, JuzExam>  $exams  oldest first
     * @return list<SheetExam>
     */
    private static function marks(Collection $exams): array
    {
        return $exams
            ->keyBy('juz_no')
            ->sortKeys()
            ->map(fn (JuzExam $exam): SheetExam => new SheetExam($exam->juz_no, $exam->isPending() ? null : $exam->grade))
            ->values()
            ->all();
    }
}
