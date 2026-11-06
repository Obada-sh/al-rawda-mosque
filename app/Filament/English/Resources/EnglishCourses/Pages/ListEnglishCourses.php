<?php

namespace App\Filament\English\Resources\EnglishCourses\Pages;

use App\Filament\English\Resources\EnglishCourses\EnglishCourseResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListEnglishCourses extends ListRecords
{
    protected static string $resource = EnglishCourseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('دورة انجليزي جديدة'),
        ];
    }
}
