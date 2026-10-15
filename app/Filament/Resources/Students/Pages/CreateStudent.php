<?php

namespace App\Filament\Resources\Students\Pages;

use App\Actions\Students\SaveStudent;
use App\Filament\Resources\Students\StudentResource;
use App\Filament\Support\Attempt;
use App\Models\Student;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateStudent extends CreateRecord
{
    protected static string $resource = StudentResource::class;

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        return Attempt::form(
            fn (): Student => app(SaveStudent::class)->handle($data),
            $this->form,
        );
    }
}
