<?php

namespace App\Filament\Resources\TeacherAttendances\Pages;

use App\Actions\Attendance\RecordManualTeacherAttendance;
use App\Filament\Pages\ScanAttendance;
use App\Filament\Resources\TeacherAttendances\TeacherAttendanceResource;
use App\Filament\Support\Attempt;
use App\Filament\Support\Options;
use App\Models\TeacherAttendance;
use App\Support\Dates;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Resources\Pages\ManageRecords;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\Icons\Heroicon;

class ManageTeacherAttendances extends ManageRecords
{
    protected static string $resource = TeacherAttendanceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('scan')
                ->label('مسح البطاقات')
                ->icon(Heroicon::OutlinedQrCode)
                ->color('gray')
                ->url(fn (): string => ScanAttendance::getUrl()),
            $this->manualAttendanceAction(),
        ];
    }

    /**
     * A teacher's attendance added by hand (the card was forgotten), for
     * today or an earlier day of a course in which they teach.
     */
    private function manualAttendanceAction(): Action
    {
        return Action::make('manual')
            ->label('إضافة حضور أستاذ يدوياً')
            ->icon(Heroicon::OutlinedPlus)
            ->modalHeading('إضافة حضور أستاذ يدوياً')
            ->modalSubmitActionLabel('تسجيل الحضور')
            ->schema([
                Select::make('teacher_id')
                    ->label('الأستاذ')
                    ->options(fn (): array => Options::teachers())
                    ->searchable()
                    ->markAsRequired()
                    ->live()
                    // The course is offered among the teacher's own: the only one is chosen for the admin.
                    ->afterStateUpdated(function (Set $set, mixed $state): void {
                        $courses = Options::coursesOfTeacher($state);
                        $set('course_id', count($courses) === 1 ? array_key_first($courses) : null);
                    }),
                Select::make('course_id')
                    ->label('الدورة')
                    ->options(fn (Get $get): array => Options::coursesOfTeacher($get('teacher_id')))
                    ->helperText('الدورات التي يشرف فيها الأستاذ على حلقة أو يساعد فيها.')
                    ->markAsRequired(),
                DatePicker::make('attended_on')
                    ->label('تاريخ الحضور')
                    ->default(fn (): string => Dates::today()->toDateString())
                    ->maxDate(fn () => Dates::today())
                    ->markAsRequired(),
            ])
            ->action(fn (array $data, Action $action, RecordManualTeacherAttendance $recordManualTeacherAttendance): TeacherAttendance => Attempt::action(
                $action,
                fn (): TeacherAttendance => $recordManualTeacherAttendance->handle($data),
            ))
            ->successNotificationTitle('تم تسجيل حضور الأستاذ');
    }
}
