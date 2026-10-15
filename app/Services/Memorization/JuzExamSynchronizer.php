<?php

namespace App\Services\Memorization;

use App\Enums\ExamOrigin;
use App\Enums\ExamStatus;
use App\Events\JuzExamOpened;
use App\Models\JuzExam;
use App\Models\Recitation;
use App\Models\Student;

/**
 * Keeps a student's juz exams in step with the pages they have memorized.
 *
 * - A juz whose every page is memorized and that has no exam yet gets a
 *   pending exam.
 * - A pending exam the system opened itself is withdrawn when its juz is
 *   no longer complete (the recitation was corrected or deleted).
 * - Exams opened by the admin, and exams already taken, are never touched.
 */
final class JuzExamSynchronizer
{
    public function __construct(private readonly JuzMap $juzMap) {}

    /**
     * @param  array<int, Recitation>  $pageOwners  page number => the recitation that owns it
     */
    public function sync(Student $student, array $pageOwners): void
    {
        $completed = $this->completedJuz($pageOwners);

        $exams = JuzExam::query()
            ->where('student_id', $student->getKey())
            ->get();

        foreach ($exams as $exam) {
            $isStale = $exam->origin === ExamOrigin::Auto
                && $exam->status === ExamStatus::Pending
                && ! isset($completed[$exam->juz_no]);

            if ($isStale) {
                $exam->delete();
            }
        }

        $examinedJuz = $exams
            ->filter(fn (JuzExam $exam): bool => $exam->exists)
            ->pluck('juz_no')
            ->all();

        foreach ($completed as $juzNo => $completingRecitation) {
            if (in_array($juzNo, $examinedJuz, true)) {
                continue;
            }

            $exam = JuzExam::query()->create([
                'student_id' => $student->getKey(),
                'juz_no' => $juzNo,
                'enrollment_id' => $completingRecitation->enrollment_id,
                'status' => ExamStatus::Pending,
                'origin' => ExamOrigin::Auto,
                'triggered_on' => $completingRecitation->recited_on->toDateString(),
            ]);

            JuzExamOpened::dispatch($exam);
        }
    }

    /**
     * The juz whose every page has an owner, with the recitation that
     * completed each one (the latest among the owners of its pages).
     *
     * @param  array<int, Recitation>  $pageOwners
     * @return array<int, Recitation> juz number => completing recitation
     */
    private function completedJuz(array $pageOwners): array
    {
        $completed = [];

        foreach ($this->juzMap->all() as $juz) {
            $latest = null;

            for ($page = $juz->from_page; $page <= $juz->to_page; $page++) {
                $owner = $pageOwners[$page] ?? null;

                if ($owner === null) {
                    continue 2;
                }

                if ($latest === null || $this->isLater($owner, $latest)) {
                    $latest = $owner;
                }
            }

            if ($latest !== null) {
                $completed[$juz->juz_no] = $latest;
            }
        }

        return $completed;
    }

    private function isLater(Recitation $candidate, Recitation $current): bool
    {
        $candidateDay = $candidate->recited_on->toDateString();
        $currentDay = $current->recited_on->toDateString();

        if ($candidateDay !== $currentDay) {
            return $candidateDay > $currentDay;
        }

        return $candidate->getKey() > $current->getKey();
    }
}
