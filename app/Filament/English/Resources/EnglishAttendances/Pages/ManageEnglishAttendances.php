<?php

namespace App\Filament\English\Resources\EnglishAttendances\Pages;

use App\Actions\English\Attendance\RecordEnglishManualAttendance;
use App\Filament\English\Pages\ScanEnglishAttendance;
use App\Filament\English\Resources\EnglishAttendances\EnglishAttendanceResource;
use App\Filament\English\Support\EnglishBulkAttendanceAction;
use App\Filament\English\Support\EnglishEnrollmentSelect;
use App\Filament\Support\Attempt;
use App\Models\English\EnglishAttendance;
use App\Support\Dates;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Resources\Pages\ManageRecords;
use Filament\Support\Icons\Heroicon;

class ManageEnglishAttendances extends ManageRecords
{
    protected static string $resource = EnglishAttendanceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('scan')
                ->label('مسح البطاقات')
                ->icon(Heroicon::OutlinedQrCode)
                ->color('gray')
                ->url(fn (): string => ScanEnglishAttendance::getUrl()),
            EnglishBulkAttendanceAction::make(),
            Action::make('manual')
                ->label('إضافة حضور يدوي')
                ->icon(Heroicon::OutlinedPlus)
                ->modalHeading('إضافة حضور يدوي')
                ->modalSubmitActionLabel('تسجيل الحضور')
                ->schema([
                    EnglishEnrollmentSelect::make(),
                    DatePicker::make('attended_on')
                        ->label('تاريخ الحضور')
                        ->default(fn (): string => Dates::today()->toDateString())
                        ->maxDate(fn () => Dates::today())
                        ->markAsRequired(),
                ])
                ->action(fn (array $data, Action $action, RecordEnglishManualAttendance $recordManual): EnglishAttendance => Attempt::action(
                    $action,
                    fn (): EnglishAttendance => $recordManual->handle($data),
                ))
                ->successNotificationTitle('تم تسجيل الحضور'),
        ];
    }
}
