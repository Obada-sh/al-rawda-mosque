<?php

namespace App\Filament\English\Resources\EnglishStudents\Pages;

use App\Filament\English\Resources\EnglishStudents\EnglishStudentResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Contracts\Support\Htmlable;

/**
 * The student's file in the English section: their details, and below
 * them their English courses, payments and results.
 */
class ViewEnglishStudent extends ViewRecord
{
    protected static string $resource = EnglishStudentResource::class;

    public function getTitle(): string|Htmlable
    {
        return (string) $this->getRecord()->getAttribute('full_name');
    }

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
