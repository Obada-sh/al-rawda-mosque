<?php

namespace App\Filament\Resources\Students\Pages;

use App\Actions\Students\SaveStudent;
use App\Filament\Resources\Students\StudentResource;
use App\Filament\Support\Attempt;
use App\Models\Student;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditStudent extends EditRecord
{
    protected static string $resource = StudentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make()->label('ملف الطالب'),
        ];
    }

    /**
     * @param  Student  $record
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        return Attempt::form(
            fn (): Student => app(SaveStudent::class)->handle($data, $record),
            $this->form,
        );
    }

    protected function getRedirectUrl(): ?string
    {
        return $this->getResourceUrl('view', ['record' => $this->getRecord()]);
    }
}
