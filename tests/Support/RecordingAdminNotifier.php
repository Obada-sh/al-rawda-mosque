<?php

namespace Tests\Support;

use App\Contracts\AdminNotifier;
use App\Models\JuzExam;

/**
 * Test double that remembers what the admin would have been told.
 */
final class RecordingAdminNotifier implements AdminNotifier
{
    /** @var list<JuzExam> */
    public array $pendingExams = [];

    /** @var list<int> */
    public array $incompleteProfileCounts = [];

    public function juzExamPending(JuzExam $exam): void
    {
        $this->pendingExams[] = $exam;
    }

    public int $backupFailures = 0;

    public function incompleteProfiles(int $count): void
    {
        $this->incompleteProfileCounts[] = $count;
    }

    public function automaticBackupFailed(): void
    {
        $this->backupFailures++;
    }
}
