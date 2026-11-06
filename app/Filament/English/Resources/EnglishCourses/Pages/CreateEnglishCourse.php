<?php

namespace App\Filament\English\Resources\EnglishCourses\Pages;

use App\Filament\English\Resources\EnglishCourses\EnglishCourseResource;
use App\Filament\English\Resources\EnglishCourses\Pages\Concerns\SavesEnglishCourse;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateEnglishCourse extends CreateRecord
{
    use SavesEnglishCourse;

    protected static string $resource = EnglishCourseResource::class;

    protected static bool $canCreateAnother = false;

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        return $this->saveCourse($data);
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'تمت إضافة الدورة. أضف قاعاتها الآن، ثم سجّل الطلاب.';
    }
}
