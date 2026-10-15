<?php

namespace App\Events;

use App\Models\JuzExam;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A juz exam became pending for a student.
 *
 * Dispatched after the surrounding transaction commits, so nobody is
 * notified about an exam that was rolled back.
 */
final class JuzExamOpened implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(public readonly JuzExam $exam) {}
}
