<?php

namespace App\Contracts;

use App\Models\JuzExam;

/**
 * Delivers in-app notifications to the admin.
 *
 * The application layer depends on this contract only, so the business
 * rules know nothing about the admin panel that displays the notification.
 */
interface AdminNotifier
{
    /**
     * A student completed a juz and now needs an exam.
     */
    public function juzExamPending(JuzExam $exam): void;

    /**
     * Periodic reminder that some student profiles are still incomplete.
     */
    public function incompleteProfiles(int $count): void;

    /**
     * The automatic backup of the day could not be taken.
     */
    public function automaticBackupFailed(): void;

    /**
     * The evening copy could not be put in the Google Drive folder.
     *
     * @param  string  $reason  in Arabic
     */
    public function driveCopyFailed(string $reason): void;

    /**
     * Old evening copies could not be removed from the Google Drive
     * folder: the admin should remove them by hand.
     *
     * @param  list<string>  $names  the file names
     */
    public function driveCopiesNotRemoved(array $names, string $folder): void;
}
