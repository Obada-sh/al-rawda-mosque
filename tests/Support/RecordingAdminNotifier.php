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

    /** @var list<string> */
    public array $driveFailures = [];

    /** @var list<array{names: list<string>, folder: string}> */
    public array $driveLeftovers = [];

    public function driveCopyFailed(string $reason): void
    {
        $this->driveFailures[] = $reason;
    }

    public function driveCopiesNotRemoved(array $names, string $folder): void
    {
        $this->driveLeftovers[] = ['names' => $names, 'folder' => $folder];
    }
}
