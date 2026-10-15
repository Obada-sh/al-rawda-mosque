<?php

namespace App\Listeners;

use App\Contracts\AdminNotifier;
use App\Events\JuzExamOpened;

final class NotifyAdminOfPendingJuzExam
{
    public function __construct(private readonly AdminNotifier $notifier) {}

    public function handle(JuzExamOpened $event): void
    {
        $this->notifier->juzExamPending($event->exam);
    }
}
