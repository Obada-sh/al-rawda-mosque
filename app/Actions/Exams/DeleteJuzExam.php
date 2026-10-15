<?php

namespace App\Actions\Exams;

use App\Models\JuzExam;

final class DeleteJuzExam
{
    public function handle(JuzExam $exam): void
    {
        $exam->delete();
    }
}
