<?php

namespace App\Filament\English\Resources\EnglishStudents\Pages;

use App\Actions\Students\SaveStudent;
use App\Enums\Section;
use App\Filament\English\Resources\EnglishStudents\EnglishStudentResource;
use App\Filament\Support\Attempt;
use App\Models\Student;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateEnglishStudent extends CreateRecord
{
    protected static string $resource = EnglishStudentResource::class;

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        return Attempt::form(
            fn (): Student => app(SaveStudent::class)->handle($data, section: Section::English),
            $this->form,
        );
    }
}
