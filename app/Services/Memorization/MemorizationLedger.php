<?php

namespace App\Services\Memorization;

use App\Models\Enrollment;
use App\Models\Recitation;
use App\Models\Student;
use App\Services\Points\PointsLedger;
use Illuminate\Support\Facades\DB;

/**
 * Single source of truth for what a student has memorized.
 *
 * A page belongs to the earliest recitation that covers it (by date, then
 * by entry order). Because the admin can back-date, correct and delete
 * recitations, ownership cannot be maintained incrementally without edge
 * cases, so after every change the student's pages are rebuilt from their
 * recitations. A student has at most a few hundred recitations and 604
 * pages, so a rebuild is cheap and always correct.
 *
 * The rebuild updates, in one transaction:
 *   - recitations.new_pages_count
 *   - student_pages
 *   - students.recited_pages_count
 *   - the points of each recitation
 *   - the student's juz exams
 */
final class MemorizationLedger
{
    private const INSERT_CHUNK = 200;

    public function __construct(
        private readonly PointsLedger $points,
        private readonly JuzExamSynchronizer $exams,
    ) {}

    public function rebuildFor(Student|int $student): void
    {
        $studentId = $student instanceof Student ? $student->getKey() : $student;

        DB::transaction(function () use ($studentId): void {
            // The row lock serialises concurrent rebuilds of the same student.
            $student = Student::query()->lockForUpdate()->findOrFail($studentId);

            $recitations = Recitation::query()
                ->whereIn(
                    'enrollment_id',
                    Enrollment::query()->where('student_id', $student->getKey())->select('id'),
                )
                ->orderBy('recited_on')
                ->orderBy('id')
                ->get();

            $owners = $this->assignPages($recitations->all());

            $this->storePages($student, $owners);

            $student->forceFill(['recited_pages_count' => count($owners)])->save();

            foreach ($recitations as $recitation) {
                $this->points->syncForRecitation($recitation);
            }

            $this->exams->sync($student, $owners);
        });
    }

    /**
     * Walks the recitations in order, gives each page to the first
     * recitation that covers it and stores each recitation's new page count.
     *
     * @param  list<Recitation>  $recitations  ordered by date then id
     * @return array<int, Recitation> page number => owning recitation
     */
    private function assignPages(array $recitations): array
    {
        $owners = [];

        foreach ($recitations as $recitation) {
            $newPages = 0;

            for ($page = $recitation->from_page; $page <= $recitation->to_page; $page++) {
                if (! isset($owners[$page])) {
                    $owners[$page] = $recitation;
                    $newPages++;
                }
            }

            if ($recitation->new_pages_count !== $newPages) {
                $recitation->forceFill(['new_pages_count' => $newPages])->save();
            }
        }

        return $owners;
    }

    /**
     * @param  array<int, Recitation>  $owners
     */
    private function storePages(Student $student, array $owners): void
    {
        DB::table('student_pages')->where('student_id', $student->getKey())->delete();

        $now = now();
        $rows = [];

        foreach ($owners as $page => $recitation) {
            $rows[] = [
                'student_id' => $student->getKey(),
                'page_no' => $page,
                'recitation_id' => $recitation->getKey(),
                'created_at' => $now,
            ];
        }

        foreach (array_chunk($rows, self::INSERT_CHUNK) as $chunk) {
            DB::table('student_pages')->insert($chunk);
        }
    }
}
