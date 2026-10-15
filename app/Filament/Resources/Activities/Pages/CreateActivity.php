<?php

namespace App\Filament\Resources\Activities\Pages;

use App\Filament\Resources\Activities\ActivityResource;
use App\Filament\Resources\Activities\Pages\Concerns\SavesActivity;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateActivity extends CreateRecord
{
    use SavesActivity;

    protected static string $resource = ActivityResource::class;

    protected static ?string $title = 'تسجيل نشاط';

    protected static bool $canCreateAnother = false;

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        return $this->saveActivity($data);
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'تم تسجيل النشاط';
    }
}
