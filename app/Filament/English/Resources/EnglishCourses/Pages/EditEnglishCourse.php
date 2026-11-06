<?php

namespace App\Filament\English\Resources\EnglishCourses\Pages;

use App\Actions\English\Courses\DeleteEnglishCourse;
use App\Filament\English\Resources\EnglishCourses\EnglishCourseResource;
use App\Filament\English\Resources\EnglishCourses\Pages\Concerns\SavesEnglishCourse;
use App\Filament\English\Support\EnglishPresenter;
use App\Filament\Support\Attempt;
use App\Filament\Support\Options;
use App\Models\English\EnglishCourse;
use App\Models\English\EnglishCourseSchedule;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;

class EditEnglishCourse extends EditRecord
{
    use SavesEnglishCourse;

    protected static string $resource = EnglishCourseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('cards')
                ->label('بطاقات الطلاب')
                ->icon(Heroicon::OutlinedPrinter)
                ->color('gray')
                ->url(fn (): string => EnglishPresenter::cardsUrl((int) $this->getRecord()->getKey()))
                ->openUrlInNewTab(),
            DeleteAction::make()
                ->modalDescription('تُحذف فقط الدورة التي ليس فيها طلاب ولا جلسات ولا امتحانات ولا أوراق. الدورة التي استُعملت تُنهى بتغيير حالتها إلى «منتهية».')
                ->using(fn (EnglishCourse $record, DeleteEnglishCourse $deleteCourse): bool => Attempt::confirmed(function () use ($record, $deleteCourse): bool {
                    $deleteCourse->handle($record);

                    return true;
                })),
        ];
    }

    /**
     * The weekly class days are stored in their own table; they are added
     * to the form data here, in week order.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var EnglishCourse $course */
        $course = $this->getRecord();

        $data['schedules'] = $course->schedules
            ->sortBy(fn (EnglishCourseSchedule $schedule): int => Options::weekPosition($schedule->weekday))
            ->map(fn (EnglishCourseSchedule $schedule): array => [
                'weekday' => $schedule->weekday->value,
                'start_time' => substr($schedule->start_time, 0, 5),
                'end_time' => substr($schedule->end_time, 0, 5),
            ])
            ->values()
            ->all();

        return $data;
    }

    /**
     * @param  EnglishCourse  $record
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        return $this->saveCourse($data, $record);
    }
}
