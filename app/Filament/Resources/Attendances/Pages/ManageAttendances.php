<?php

namespace App\Filament\Resources\Attendances\Pages;

use App\Actions\Attendance\RecordManualAttendance;
use App\Filament\Pages\ScanAttendance;
use App\Filament\Resources\Attendances\AttendanceResource;
use App\Filament\Support\Attempt;
use App\Filament\Support\Attendance\BulkAttendanceAction;
use App\Filament\Support\Fields\EnrollmentSelect;
use App\Models\Attendance;
use App\Support\Dates;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Resources\Pages\ManageRecords;
use Filament\Support\Icons\Heroicon;

class ManageAttendances extends ManageRecords
{
    protected static string $resource = AttendanceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('scan')
                ->label('مسح البطاقات')
                ->icon(Heroicon::OutlinedQrCode)
                ->color('gray')
                ->url(fn (): string => ScanAttendance::getUrl()),
            BulkAttendanceAction::make(),
            $this->manualAttendanceAction(),
        ];
    }

    /**
     * Attendance added by hand, for today or any earlier day. It earns the
     * same points as a scan.
     */
    private function manualAttendanceAction(): Action
    {
        return Action::make('manual')
            ->label('إضافة حضور يدوي')
            ->icon(Heroicon::OutlinedPlus)
            ->modalHeading('إضافة حضور يدوي')
            ->modalSubmitActionLabel('تسجيل الحضور')
            ->schema([
                EnrollmentSelect::make(),
                DatePicker::make('attended_on')
                    ->label('تاريخ الحضور')
                    ->default(fn (): string => Dates::today()->toDateString())
                    ->maxDate(fn () => Dates::today())
                    ->markAsRequired(),
            ])
            ->action(fn (array $data, Action $action, RecordManualAttendance $recordManualAttendance): Attendance => Attempt::action(
                $action,
                fn (): Attendance => $recordManualAttendance->handle($data),
            ))
            ->successNotificationTitle('تم تسجيل الحضور');
    }
}
