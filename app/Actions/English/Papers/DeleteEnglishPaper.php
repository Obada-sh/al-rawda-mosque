<?php

namespace App\Actions\English\Papers;

use App\Models\English\EnglishPaper;
use App\Services\English\EnglishPapers;

/**
 * Deletes a paper of an English course, with its file.
 */
final class DeleteEnglishPaper
{
    public function __construct(private readonly EnglishPapers $papers) {}

    public function handle(EnglishPaper $paper): void
    {
        $paper->delete();

        $this->papers->remove($paper->file_path);
    }
}
