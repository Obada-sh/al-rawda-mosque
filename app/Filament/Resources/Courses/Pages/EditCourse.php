<?php

namespace App\Filament\Resources\Courses\Pages;

use App\Actions\Courses\DeleteCourse;
use App\Filament\Resources\Courses\CourseResource;
use App\Filament\Resources\Courses\Pages\Concerns\SavesCourse;
use App\Filament\Support\Attempt;
use App\Filament\Support\Cards\CardPrintActions;
use App\Filament\Support\Options;
use App\Models\Course;
use App\Models\CourseSchedule;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditCourse extends EditRecord
{
    use SavesCourse;

    protected static string $resource = CourseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CardPrintActions::forCourse(fn (): Course => $this->getRecord()),
            CardPrintActions::forCourseTeachers(fn (): Course => $this->getRecord()),
            DeleteAction::make()
                ->modalDescription('تُحذف فقط الدورة التي ليس فيها طلاب ولا جلسات. الدورة التي استُعملت تُنهى بتغيير حالتها إلى «منتهية».')
                ->using(fn (Course $record, DeleteCourse $deleteCourse): bool => Attempt::confirmed(function () use ($record, $deleteCourse): bool {
                    $deleteCourse->handle($record);

                    return true;
                })),
        ];
    }

    /**
     * The weekly timetable is stored in its own table; it is added to the
     * form data here, in week order.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var Course $course */
        $course = $this->getRecord();

        $data['schedules'] = $course->schedules
            ->sortBy(fn (CourseSchedule $schedule): int => Options::weekPosition($schedule->weekday))
            ->map(fn (CourseSchedule $schedule): array => [
                'weekday' => $schedule->weekday->value,
                'start_time' => substr($schedule->start_time, 0, 5),
                'end_time' => substr($schedule->end_time, 0, 5),
            ])
            ->values()
            ->all();

        return $data;
    }

    /**
     * @param  Course  $record
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        return $this->saveCourse($data, $record);
    }
}
