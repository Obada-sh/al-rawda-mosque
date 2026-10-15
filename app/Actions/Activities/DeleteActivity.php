<?php

namespace App\Actions\Activities;

use App\Models\Activity;

/**
 * Removes an activity recorded by mistake, with the list of those who
 * attended and the points it gave them (the database cascades both).
 */
final class DeleteActivity
{
    public function handle(Activity $activity): void
    {
        $activity->delete();
    }
}
