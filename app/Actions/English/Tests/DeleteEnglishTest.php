<?php

namespace App\Actions\English\Tests;

use App\Models\English\EnglishTest;

/**
 * Deletes a test together with the marks given in it (the admin is told
 * so before confirming).
 */
final class DeleteEnglishTest
{
    public function handle(EnglishTest $test): void
    {
        // The results go with the test (cascade).
        $test->delete();
    }
}
