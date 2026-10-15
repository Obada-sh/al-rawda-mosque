<?php

namespace App\Filament\Resources\Courses\Pages;

use App\Filament\Resources\Courses\CourseResource;
use App\Filament\Resources\Courses\Pages\Concerns\SavesCourse;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateCourse extends CreateRecord
{
    use SavesCourse;

    protected static string $resource = CourseResource::class;

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
        return 'تمت إضافة الدورة. أضف حلقاتها الآن.';
    }
}
