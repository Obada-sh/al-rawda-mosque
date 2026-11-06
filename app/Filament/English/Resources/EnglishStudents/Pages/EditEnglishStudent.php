<?php

namespace App\Filament\English\Resources\EnglishStudents\Pages;

use App\Actions\Students\SaveStudent;
use App\Enums\Section;
use App\Filament\English\Resources\EnglishStudents\EnglishStudentResource;
use App\Filament\Support\Attempt;
use App\Models\Student;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditEnglishStudent extends EditRecord
{
    protected static string $resource = EnglishStudentResource::class;

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
            fn (): Student => app(SaveStudent::class)->handle($data, $record, Section::English),
            $this->form,
        );
    }

    protected function getRedirectUrl(): ?string
    {
        return $this->getResourceUrl('view', ['record' => $this->getRecord()]);
    }
}
