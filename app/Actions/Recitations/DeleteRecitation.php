<?php

namespace App\Actions\Recitations;

use App\Models\Recitation;
use App\Services\Memorization\MemorizationLedger;
use Illuminate\Support\Facades\DB;

/**
 * Deletes a recitation and recomputes the student's pages, points and
 * exams. Pages it owned pass to the next recitation that covers them.
 */
final class DeleteRecitation
{
    public function __construct(private readonly MemorizationLedger $ledger) {}

    public function handle(Recitation $recitation): void
    {
        DB::transaction(function () use ($recitation): void {
            $studentId = $recitation->enrollment()->value('student_id');

            $recitation->pointTransaction()->delete();
            $recitation->ownedPages()->delete();
            $recitation->delete();

            $this->ledger->rebuildFor((int) $studentId);
        });
    }
}
