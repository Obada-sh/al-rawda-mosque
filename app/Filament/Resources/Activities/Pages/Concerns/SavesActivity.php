<?php

namespace App\Filament\Resources\Activities\Pages\Concerns;

use App\Actions\Activities\SaveActivity;
use App\Filament\Support\Attempt;
use App\Models\Activity;

/**
 * Saving the activity form, shared by the create and the edit page.
 */
trait SavesActivity
{
    /**
     * @param  array<string, mixed>  $data
     */
    protected function saveActivity(array $data, ?Activity $activity = null): Activity
    {
        return Attempt::form(
            fn (): Activity => app(SaveActivity::class)->handle($data, $activity),
            $this->form,
        );
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResourceUrl('index');
    }
}
